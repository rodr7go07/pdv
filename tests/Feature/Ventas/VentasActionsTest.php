<?php

use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\MovimientoInventario;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->depto = Departamento::factory()->create();
    $this->pieza = UnidadMedida::factory()->create();
    $this->kilo = UnidadMedida::factory()->decimal()->create();
    $this->iva = Impuesto::factory()->iva('16')->create();

    $this->leche = Articulo::factory()->create([
        'clave' => 'LECHE', 'descripcion' => 'LECHE 1L', 'departamento_id' => $this->depto->id, 'unidad_medida_id' => $this->pieza->id,
        'costo' => '15', 'precio_1' => '25', 'precio_2' => '23', 'granel' => false,
    ]);
    $this->refresco = Articulo::factory()->create([
        'clave' => 'COLA', 'descripcion' => 'COLA 600', 'departamento_id' => $this->depto->id, 'unidad_medida_id' => $this->pieza->id,
        'costo' => '10', 'precio_1' => '116',
    ]);
    $this->refresco->impuestos()->sync([$this->iva->id]);
    $this->manzana = Articulo::factory()->create([
        'clave' => 'MANZ', 'descripcion' => 'MANZANA', 'departamento_id' => $this->depto->id, 'unidad_medida_id' => $this->kilo->id,
        'costo' => '20', 'precio_1' => '40', 'granel' => true,
    ]);

    $this->vender = app(RegistrarVenta::class);
});

function pago(string $forma, string $monto, ?string $ref = null): array
{
    return ['forma' => $forma, 'monto' => $monto, 'referencia' => $ref];
}

function venderTodo(array $lineas, array $pagos, array $global = [], ?User $usuario = null, int $lista = 1): Venta
{
    return app(RegistrarVenta::class)(test()->caja, $usuario ?? test()->cajero, $lineas, $pagos, $global, $lista);
}

// ─── Venta ───────────────────────────────────────────────────────────────────

it('registra una venta completa con partidas, pago, folio e inventario', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [pago('efectivo', '100')]);

    expect($venta->folio)->toBe('A-000001')
        ->and($venta->total)->toBe('50.00')
        ->and($venta->efectivo_recibido)->toBe('100.00')
        ->and($venta->cambio)->toBe('50.00')
        ->and($venta->estado)->toBe('completada')
        ->and($venta->partidas)->toHaveCount(1)
        ->and($venta->pagos->pluck('forma')->all())->toBe(['efectivo'])
        ->and($venta->pagos[0]->monto)->toBe('50.00')
        ->and($this->leche->refresh()->existencia)->toBe('-2.000');

    $mov = MovimientoInventario::first();
    expect($mov->tipo)->toBe(TipoMovimiento::Venta)->and($mov->cantidad)->toBe('-2.000')->and($mov->costo_unitario)->toBe('15.0000');
});

it('guarda la foto del momento y no cambia al subir el precio después', function () {
    $venta = venderTodo([['articulo_id' => $this->refresco->id, 'cantidad' => '1']], [pago('tarjeta', '116')]);

    $this->refresco->update(['precio_1' => '200', 'descripcion' => 'OTRO NOMBRE', 'costo' => '99']);

    $p = $venta->fresh()->partidas[0];
    expect($p->descripcion)->toBe('COLA 600')->and($p->precio_unitario)->toBe('116.00')->and($p->costo_unitario)->toBe('10.0000')
        ->and($p->subtotal)->toBe('100.00')->and($p->impuestos)->toBe('16.00')->and($p->tasa_impuestos)->toBe('16.0000')
        ->and($p->impuestos_detalle)->toBe([['nombre' => 'IVA 16%', 'tasa' => '16', 'monto' => '16.00']])
        ->and($venta->fresh()->total)->toBe('116.00');
});

it('ignora los precios de la pantalla y usa los de la base de datos', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1', 'precio' => '0.01', 'precio_unitario' => '0.01']], [pago('efectivo', '25')]);

    expect($venta->total)->toBe('25.00');
});

it('usa la lista de precios elegida y cae al precio 1 si no existe', function () {
    $v2 = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '23')], lista: 2);
    $v3 = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '25')], lista: 3); // sin precio 3

    expect($v2->total)->toBe('23.00')->and($v3->total)->toBe('25.00');
});

it('vende a granel con decimales y respeta unidades enteras', function () {
    $venta = venderTodo([['articulo_id' => $this->manzana->id, 'cantidad' => '0.375']], [pago('efectivo', '15')]);
    expect($venta->total)->toBe('15.00')->and($venta->partidas[0]->cantidad)->toBe('0.375');

    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1.5']], [pago('efectivo', '100')]))->toThrow(ValidationException::class);
    expect(fn () => venderTodo([['articulo_id' => $this->manzana->id, 'cantidad' => '0.0001']], [pago('efectivo', '100')]))->toThrow(ValidationException::class);
});

it('cobra con pago mixto: tarjeta exacta y el resto en efectivo con cambio', function () {
    $venta = venderTodo(
        [['articulo_id' => $this->leche->id, 'cantidad' => '4']],           // 100.00
        [pago('tarjeta', '40', 'AUT123'), pago('efectivo', '100')],
    );

    expect($venta->pagos->pluck('monto', 'forma')->all())->toBe(['efectivo' => '60.00', 'tarjeta' => '40.00'])
        ->and($venta->efectivo_recibido)->toBe('100.00')->and($venta->cambio)->toBe('40.00')
        ->and($venta->pagos->firstWhere('forma', 'tarjeta')->referencia)->toBe('AUT123');
});

it('valida los pagos', function (array $pagos) {
    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '2']], $pagos))->toThrow(ValidationException::class);

    expect(Venta::count())->toBe(0)->and(MovimientoInventario::count())->toBe(0);
})->with([
    'efectivo insuficiente' => [[['forma' => 'efectivo', 'monto' => '49.99']]],
    'tarjeta mayor al total' => [[['forma' => 'tarjeta', 'monto' => '60']]],
    'efectivo sobrando con todo en tarjeta' => [[['forma' => 'tarjeta', 'monto' => '50'], ['forma' => 'efectivo', 'monto' => '10']]],
    'forma inválida' => [[['forma' => 'cheque', 'monto' => '50']]],
    'sin pagos' => [[]],
    'monto cero' => [[['forma' => 'efectivo', 'monto' => '0']]],
    'monto negativo' => [[['forma' => 'efectivo', 'monto' => '-50']]],
]);

it('valida las partidas', function (array $lineas) {
    expect(fn () => venderTodo($lineas, [pago('efectivo', '1000')]))->toThrow(ValidationException::class);
})->with([
    'sin partidas' => [[]],
    'cantidad cero' => [[['articulo_id' => 1, 'cantidad' => '0']]],
    'cantidad negativa' => [[['articulo_id' => 1, 'cantidad' => '-2']]],
    'artículo inexistente' => [[['articulo_id' => 99999, 'cantidad' => '1']]],
]);

it('no vende artículos inactivos ni eliminados', function () {
    $this->leche->update(['activo' => false]);
    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '50')]))->toThrow(ValidationException::class);

    $this->refresco->delete();
    expect(fn () => venderTodo([['articulo_id' => $this->refresco->id, 'cantidad' => '1']], [pago('efectivo', '500')]))->toThrow(ValidationException::class);
});

it('no vende en una caja desactivada', function () {
    $this->caja->update(['activa' => false]);

    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '50')]))->toThrow(ValidationException::class);
});

it('puede impedir ventas sin existencia', function () {
    Configuracion::actual()->update(['vender_sin_existencia' => false]);

    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '50')]))->toThrow(ValidationException::class);

    app(App\Actions\Inventario\RegistrarMovimientoInventario::class)($this->leche, TipoMovimiento::Ajuste, '3');

    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '2'], ['articulo_id' => $this->leche->id, 'cantidad' => '2']], [pago('efectivo', '100')]))
        ->toThrow(ValidationException::class); // 4 > 3 entre las dos partidas

    venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '3']], [pago('efectivo', '75')]);
    expect($this->leche->refresh()->existencia)->toBe('0.000');
});

it('no genera movimientos de inventario para artículos sin inventario', function () {
    $servicio = Articulo::factory()->create(['inventariable' => false, 'precio_1' => '30', 'departamento_id' => $this->depto->id, 'unidad_medida_id' => $this->pieza->id]);

    venderTodo([['articulo_id' => $servicio->id, 'cantidad' => '1']], [pago('efectivo', '30')]);

    expect(MovimientoInventario::count())->toBe(0);
});

it('asigna folios consecutivos por caja', function () {
    $otra = Caja::factory()->create(['serie' => 'B']);
    $l = [['articulo_id' => $this->leche->id, 'cantidad' => '1']];

    $a1 = venderTodo($l, [pago('efectivo', '25')]);
    $a2 = venderTodo($l, [pago('efectivo', '25')]);
    $b1 = app(RegistrarVenta::class)($otra, $this->cajero, $l, [pago('efectivo', '25')]);

    expect([$a1->folio, $a2->folio, $b1->folio])->toBe(['A-000001', 'A-000002', 'B-000001']);
});

it('revierte todo si algo falla a la mitad', function () {
    expect(fn () => venderTodo(
        [['articulo_id' => $this->leche->id, 'cantidad' => '1'], ['articulo_id' => $this->leche->id, 'cantidad' => '2']],
        [pago('efectivo', '10')], // no alcanza
    ))->toThrow(ValidationException::class);

    expect(Venta::count())->toBe(0)->and($this->leche->refresh()->existencia)->toBe('0.000')->and(DB::table('folios')->where('serie', 'A')->value('ultimo'))->toBeNull();
});

it('registra una venta de 50 partidas sin degradación', function () {
    $articulos = Articulo::factory()->count(50)->create([
        'departamento_id' => $this->depto->id, 'unidad_medida_id' => $this->pieza->id, 'precio_1' => '19.90', 'costo' => '10',
    ]);
    foreach ($articulos->take(10) as $a) {
        $a->impuestos()->sync([$this->iva->id]);
    }

    $lineas = $articulos->map(fn ($a) => ['articulo_id' => $a->id, 'cantidad' => '2'])->all();

    $consultas = 0;
    DB::listen(function () use (&$consultas) { $consultas++; });

    $inicio = microtime(true);
    $venta = venderTodo($lineas, [pago('efectivo', '2000')]);
    $segundos = microtime(true) - $inicio;

    expect($venta->partidas)->toHaveCount(50)
        ->and($venta->total)->toBe('1990.00')
        ->and($consultas)->toBeLessThan(450)   // sin consultas N+1 desbocadas
        ->and($segundos)->toBeLessThan(5.0);

    expect(MovimientoInventario::count())->toBe(50);
});

// ─── Descuentos y permisos ───────────────────────────────────────────────────

it('el cajero no puede dar descuentos pero el encargado sí', function () {
    $linea = [['articulo_id' => $this->leche->id, 'cantidad' => '2', 'descuento_tipo' => 'pct', 'descuento_valor' => '10']];

    expect(fn () => venderTodo($linea, [pago('efectivo', '100')], usuario: $this->cajero))->toThrow(ValidationException::class);

    $v = venderTodo($linea, [pago('efectivo', '100')], usuario: $this->encargado);
    expect($v->total)->toBe('45.00')->and($v->descuento)->toBe('5.00');
});

it('aplica descuento general', function () {
    $v = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '4']], [pago('efectivo', '100')], ['tipo' => 'monto', 'valor' => '10'], $this->encargado);

    expect($v->total)->toBe('90.00')->and($v->descuento)->toBe('10.00')->and($v->partidas[0]->descuento)->toBe('10.00');
});

it('rechaza descuentos mayores al 100%', function () {
    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1', 'descuento_tipo' => 'pct', 'descuento_valor' => '150']], [pago('efectivo', '50')], usuario: $this->encargado))
        ->toThrow(ValidationException::class);
});

it('un descuento del 100% deja el total en cero y se rechaza', function () {
    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '50')], ['tipo' => 'pct', 'valor' => '100'], $this->encargado))
        ->toThrow(ValidationException::class);
});

it('quien no puede cobrar recibe un 403', function () {
    $sinPermiso = User::factory()->create();

    expect(fn () => venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '50')], usuario: $sinPermiso))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// ─── Cancelación ─────────────────────────────────────────────────────────────

it('cancela una venta regresando el inventario y dejando motivo, usuario y fecha', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '3']], [pago('efectivo', '75')]);

    $cancelada = app(CancelarVenta::class)($venta, 'El cliente se arrepintió', $this->encargado);

    expect($cancelada->estado)->toBe('cancelada')->and($cancelada->cancelada_por)->toBe($this->encargado->id)
        ->and($cancelada->cancelada_at)->not->toBeNull()->and($cancelada->motivo_cancelacion)->toContain('arrepintió')
        ->and($this->leche->refresh()->existencia)->toBe('0.000')
        ->and(Venta::count())->toBe(1)
        ->and(MovimientoInventario::where('tipo', TipoMovimiento::CancelacionVenta->value)->count())->toBe(1);
});

it('la cancelación exige permiso, motivo y no se repite', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '25')]);

    expect(fn () => app(CancelarVenta::class)($venta, 'x', $this->cajero))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and(fn () => app(CancelarVenta::class)($venta, '   ', $this->encargado))->toThrow(ValidationException::class);

    app(CancelarVenta::class)($venta, 'Error', $this->encargado);

    expect(fn () => app(CancelarVenta::class)($venta->refresh(), 'Otra vez', $this->encargado))->toThrow(ValidationException::class)
        ->and($this->leche->refresh()->existencia)->toBe('0.000');
});

it('no se puede cancelar una venta con devoluciones', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [pago('efectivo', '50')]);
    app(RegistrarDevolucion::class)($venta, [['partida_id' => $venta->partidas[0]->id, 'cantidad' => '1']], 'Dañado', $this->encargado);

    expect(fn () => app(CancelarVenta::class)($venta->refresh(), 'x', $this->encargado))->toThrow(ValidationException::class);
});

// ─── Devoluciones ────────────────────────────────────────────────────────────

it('devuelve parte de una venta: reembolsa lo cobrado y regresa piezas al inventario', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '4']], [pago('efectivo', '100')], ['tipo' => 'monto', 'valor' => '10'], $this->encargado);
    // 4 × 25 = 100 − 10 descuento = 90 → 22.50 por pieza

    $d = app(RegistrarDevolucion::class)($venta, [['partida_id' => $venta->partidas[0]->id, 'cantidad' => '1']], 'No le gustó', $this->encargado);

    $p = $venta->partidas[0]->fresh();
    expect($d->folio)->toBe('D-000001')->and($d->total)->toBe('22.50')->and($p->cantidad_devuelta)->toBe('1.000')->and($p->monto_devuelto)->toBe('22.50')
        ->and($p->cantidadDevolvible())->toBe('3.000')
        ->and($this->leche->refresh()->existencia)->toBe('-3.000') // -4 de la venta +1 devuelta
        ->and(MovimientoInventario::where('tipo', TipoMovimiento::Devolucion->value)->count())->toBe(1);
});

it('la última pieza devuelta absorbe el redondeo y el total devuelto nunca pasa de lo cobrado', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '3']], [pago('efectivo', '100')], ['tipo' => 'monto', 'valor' => '0.01'], $this->encargado);
    // 75.00 − 0.01 = 74.99 → 24.9966… por pieza
    $id = $venta->partidas[0]->id;
    $dev = app(RegistrarDevolucion::class);

    $total = '0';
    foreach ([1, 1, 1] as $_) {
        $total = bcadd($total, $dev($venta->refresh(), [['partida_id' => $id, 'cantidad' => '1']], 'x', $this->encargado)->total, 2);
    }

    expect($total)->toBe('74.99');
});

it('no permite devolver más de lo vendido ni de otra venta', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [pago('efectivo', '50')]);
    $otra = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '25')]);
    $dev = app(RegistrarDevolucion::class);
    $id = $venta->partidas[0]->id;

    expect(fn () => $dev($venta, [['partida_id' => $id, 'cantidad' => '3']], 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $dev($venta, [['partida_id' => $otra->partidas[0]->id, 'cantidad' => '1']], 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $dev($venta, [['partida_id' => $id, 'cantidad' => '1.5']], 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $dev($venta, [['partida_id' => $id, 'cantidad' => '0']], 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $dev($venta, [['partida_id' => $id, 'cantidad' => '1']], '', $this->encargado))->toThrow(ValidationException::class);

    $dev($venta, [['partida_id' => $id, 'cantidad' => '2']], 'Todo', $this->encargado);
    expect(fn () => $dev($venta->refresh(), [['partida_id' => $id, 'cantidad' => '1']], 'x', $this->encargado))->toThrow(ValidationException::class);
});

it('no se devuelve una venta cancelada y exige permiso', function () {
    $venta = venderTodo([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [pago('efectivo', '25')]);
    $id = $venta->partidas[0]->id;

    expect(fn () => app(RegistrarDevolucion::class)($venta, [['partida_id' => $id, 'cantidad' => '1']], 'x', $this->cajero))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    app(CancelarVenta::class)($venta, 'Error', $this->encargado);

    expect(fn () => app(RegistrarDevolucion::class)($venta->refresh(), [['partida_id' => $id, 'cantidad' => '1']], 'x', $this->encargado))->toThrow(ValidationException::class);
});
