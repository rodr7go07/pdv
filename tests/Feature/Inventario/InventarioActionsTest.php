<?php

use App\Actions\Compras\CancelarCompra;
use App\Actions\Compras\RegistrarCompra;
use App\Actions\Inventario\AjustarInventario;
use App\Actions\Inventario\GestionarConteo;
use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\MetodoCosteo;
use App\Enums\MotivoAjuste;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Configuracion;
use App\Models\HistorialPrecio;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Folios;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->usuario = User::factory()->create();
    $this->proveedor = Proveedor::factory()->create();
    $this->articulo = Articulo::factory()->create(['costo' => '0', 'precio_1' => '20']);
    $this->movimiento = app(RegistrarMovimientoInventario::class);
});

function comprar(array $partidas, ?Proveedor $proveedor = null, ?string $fecha = null): Compra
{
    return app(RegistrarCompra::class)([
        'proveedor_id' => ($proveedor ?? test()->proveedor)->id,
        'fecha' => $fecha ?? now()->toDateString(),
        'factura' => 'F-1',
    ], $partidas, test()->usuario);
}

// ─── Folios ──────────────────────────────────────────────────────────────────

it('genera folios consecutivos por serie', function () {
    DB::transaction(function () {
        expect(Folios::siguiente('A'))->toBe(1)
            ->and(Folios::siguiente('A'))->toBe(2)
            ->and(Folios::siguiente('B'))->toBe(1)
            ->and(Folios::formato('A', 42))->toBe('A-000042');
    });
});

// ─── Movimientos ─────────────────────────────────────────────────────────────

it('actualiza la existencia y el kardex en cada movimiento', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Ajuste, '10', usuario: $this->usuario, motivo: 'correccion');
    $m = ($this->movimiento)($this->articulo, TipoMovimiento::Venta, '-3.5', usuario: $this->usuario);

    expect($this->articulo->refresh()->existencia)->toBe('6.500')
        ->and($m->existencia_resultante)->toBe('6.500')
        ->and($m->cantidad)->toBe('-3.500')
        ->and(MovimientoInventario::where('articulo_id', $this->articulo->id)->count())->toBe(2);
});

it('permite existencia negativa si se vende sin existencia', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Venta, '-2');

    expect($this->articulo->refresh()->existencia)->toBe('-2.000');
});

it('no genera movimientos para artículos sin inventario', function () {
    $servicio = Articulo::factory()->create(['inventariable' => false]);

    expect(($this->movimiento)($servicio, TipoMovimiento::Venta, '-1'))->toBeNull()
        ->and(MovimientoInventario::count())->toBe(0)
        ->and($servicio->refresh()->existencia)->toBe('0.000');
});

it('la existencia no es asignable directamente', function () {
    $this->articulo->fill(['existencia' => '99'])->save();

    expect($this->articulo->refresh()->existencia)->toBe('0.000');
});

// ─── Compras y costeo ────────────────────────────────────────────────────────

it('registra una compra con folio, existencia, kardex y costos', function () {
    $compra = comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '12.50']]);

    $this->articulo->refresh();

    expect($compra->folio)->toBe('C-000001')
        ->and($compra->total)->toBe('125.00')
        ->and($compra->partidas)->toHaveCount(1)
        ->and($this->articulo->existencia)->toBe('10.000')
        ->and($this->articulo->ultimo_costo)->toBe('12.5000')
        ->and($this->articulo->costo_promedio)->toBe('12.5000')
        ->and($this->articulo->costo)->toBe('12.5000');

    $mov = MovimientoInventario::first();
    expect($mov->tipo)->toBe(TipoMovimiento::Compra)->and($mov->referencia_id)->toBe($compra->id);
});

it('con costo promedio mezcla el inventario existente con la compra nueva', function () {
    Configuracion::actual()->update(['metodo_costeo' => MetodoCosteo::Promedio]);

    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '10']]);
    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '20']]);

    $a = $this->articulo->refresh();
    expect($a->costo_promedio)->toBe('15.0000')->and($a->ultimo_costo)->toBe('20.0000')->and($a->costo)->toBe('15.0000')
        ->and($a->existencia)->toBe('20.000');
});

it('con último costo el costo vigente es el de la compra más reciente', function () {
    Configuracion::actual()->update(['metodo_costeo' => MetodoCosteo::Ultimo]);

    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '10']]);
    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '20']]);

    $a = $this->articulo->refresh();
    expect($a->costo)->toBe('20.0000')->and($a->costo_promedio)->toBe('15.0000');
});

it('guarda ambos costos aunque cambie el método después', function () {
    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '5', 'costo_unitario' => '8']]);

    $a = $this->articulo->refresh();
    expect($a->ultimo_costo)->toBe('8.0000')->and($a->costo_promedio)->toBe('8.0000');
});

it('ignora existencia negativa al calcular el promedio', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Venta, '-5');

    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '9']]);

    expect($this->articulo->refresh()->costo_promedio)->toBe('9.0000')->and($this->articulo->existencia)->toBe('5.000');
});

it('registra el cambio de costo en el historial', function () {
    comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '1', 'costo_unitario' => '7']]);

    $h = HistorialPrecio::where('articulo_id', $this->articulo->id)->where('campo', 'costo')->first();
    expect($h->origen)->toBe('compra')->and($h->valor_nuevo)->toBe('7.0000');
});

it('consolida renglones repetidos del mismo artículo', function () {
    $compra = comprar([
        ['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '10'],
        ['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '20'],
    ]);

    expect($compra->partidas)->toHaveCount(1)
        ->and($compra->partidas[0]->cantidad)->toBe('20.000')
        ->and($compra->partidas[0]->costo_unitario)->toBe('15.0000')
        ->and($compra->total)->toBe('300.00');
});

it('asigna folios consecutivos', function () {
    $a = comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '1', 'costo_unitario' => '1']]);
    $b = comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '1', 'costo_unitario' => '1']]);

    expect([$a->folio, $b->folio])->toBe(['C-000001', 'C-000002']);
});

it('valida la compra y no guarda nada si falla', function (array $partidas, array $extra) {
    $datos = array_merge(['proveedor_id' => $this->proveedor->id, 'fecha' => now()->toDateString()], $extra);

    expect(fn () => app(RegistrarCompra::class)($datos, $partidas, $this->usuario))->toThrow(ValidationException::class);

    expect(Compra::count())->toBe(0)->and(MovimientoInventario::count())->toBe(0);
})->with([
    'sin partidas' => [[], []],
    'cantidad cero' => [[['articulo_id' => 1, 'cantidad' => '0', 'costo_unitario' => '5']], []],
    'costo negativo' => [[['articulo_id' => 1, 'cantidad' => '1', 'costo_unitario' => '-5']], []],
    'artículo inexistente' => [[['articulo_id' => 999, 'cantidad' => '1', 'costo_unitario' => '5']], []],
    'fecha futura' => [[['articulo_id' => 1, 'cantidad' => '1', 'costo_unitario' => '5']], ['fecha' => '2999-01-01']],
    'proveedor inexistente' => [[['articulo_id' => 1, 'cantidad' => '1', 'costo_unitario' => '5']], ['proveedor_id' => 999]],
]);

it('revierte todo si una partida falla a la mitad', function () {
    $otro = Articulo::factory()->create();

    expect(fn () => comprar([
        ['articulo_id' => $this->articulo->id, 'cantidad' => '5', 'costo_unitario' => '5'],
        ['articulo_id' => $otro->id, 'cantidad' => '5', 'costo_unitario' => 'abc'],
    ]))->toThrow(ValidationException::class);

    expect($this->articulo->refresh()->existencia)->toBe('0.000')->and(Compra::count())->toBe(0);
});

it('cancela una compra regresando la existencia y dejando el motivo', function () {
    $compra = comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '10']]);

    $cancelada = app(CancelarCompra::class)($compra, 'Se capturó el proveedor equivocado', $this->usuario);

    expect($cancelada->estado)->toBe('cancelada')
        ->and($cancelada->cancelada_por)->toBe($this->usuario->id)
        ->and($cancelada->motivo_cancelacion)->toContain('proveedor')
        ->and($this->articulo->refresh()->existencia)->toBe('0.000')
        ->and(MovimientoInventario::where('tipo', TipoMovimiento::CancelacionCompra->value)->count())->toBe(1)
        ->and(Compra::count())->toBe(1);
});

it('no permite cancelar dos veces ni sin motivo', function () {
    $compra = comprar([['articulo_id' => $this->articulo->id, 'cantidad' => '10', 'costo_unitario' => '10']]);

    expect(fn () => app(CancelarCompra::class)($compra, '  ', $this->usuario))->toThrow(ValidationException::class);

    app(CancelarCompra::class)($compra, 'Error', $this->usuario);

    expect(fn () => app(CancelarCompra::class)($compra->refresh(), 'Otra vez', $this->usuario))->toThrow(ValidationException::class)
        ->and($this->articulo->refresh()->existencia)->toBe('0.000');
});

// ─── Ajustes ─────────────────────────────────────────────────────────────────

it('ajusta el inventario con motivo', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Ajuste, '10');

    $m = app(AjustarInventario::class)($this->articulo, 'salida', '3', MotivoAjuste::Merma, 'Se cayó la caja', $this->usuario);

    expect($this->articulo->refresh()->existencia)->toBe('7.000')
        ->and($m->motivo)->toBe('merma')
        ->and($m->nota)->toBe('Se cayó la caja')
        ->and($m->cantidad)->toBe('-3.000');
});

it('no permite una entrada por merma, caducidad o robo', function (MotivoAjuste $motivo) {
    expect(fn () => app(AjustarInventario::class)($this->articulo, 'entrada', '1', $motivo, null, $this->usuario))
        ->toThrow(ValidationException::class);
})->with([MotivoAjuste::Merma, MotivoAjuste::Caducidad, MotivoAjuste::Robo]);

it('el motivo "otro" exige nota y la cantidad debe ser positiva', function () {
    expect(fn () => app(AjustarInventario::class)($this->articulo, 'entrada', '1', MotivoAjuste::Otro, null, $this->usuario))->toThrow(ValidationException::class)
        ->and(fn () => app(AjustarInventario::class)($this->articulo, 'entrada', '0', MotivoAjuste::Correccion, null, $this->usuario))->toThrow(ValidationException::class)
        ->and(fn () => app(AjustarInventario::class)($this->articulo, 'entrada', '-4', MotivoAjuste::Correccion, null, $this->usuario))->toThrow(ValidationException::class);
});

it('no admite decimales en unidades enteras ni ajustes a artículos sin inventario', function () {
    expect(fn () => app(AjustarInventario::class)($this->articulo, 'entrada', '1.5', MotivoAjuste::Correccion, null, $this->usuario))->toThrow(ValidationException::class);

    $servicio = Articulo::factory()->create(['inventariable' => false]);
    expect(fn () => app(AjustarInventario::class)($servicio, 'entrada', '1', MotivoAjuste::Correccion, null, $this->usuario))->toThrow(ValidationException::class);

    $kilo = Articulo::factory()->create(['unidad_medida_id' => UnidadMedida::factory()->decimal()->create()->id]);
    app(AjustarInventario::class)($kilo, 'entrada', '1.5', MotivoAjuste::Correccion, null, $this->usuario);
    expect($kilo->refresh()->existencia)->toBe('1.500');
});

// ─── Conteo físico ───────────────────────────────────────────────────────────

it('el conteo ajusta solo la diferencia contra lo que decía el sistema al contar', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Ajuste, '10'); // sistema: 10
    $gestor = app(GestionarConteo::class);

    $conteo = $gestor->crear('Conteo de bodega', $this->usuario);
    $gestor->capturar($conteo, $this->articulo, '8');            // contó 8 con el sistema en 10 => faltan 2

    // Mientras se contaba, se vendieron 3 piezas
    ($this->movimiento)($this->articulo, TipoMovimiento::Venta, '-3'); // sistema: 7

    $resultado = $gestor->aplicar($conteo, $this->usuario);

    // Se aplica la diferencia (-2) sobre lo que hay hoy (7): queda 5, sin perder la venta
    expect($resultado)->toBe(['ajustados' => 1, 'sin_diferencia' => 0])
        ->and($this->articulo->refresh()->existencia)->toBe('5.000')
        ->and($conteo->refresh()->estado)->toBe('aplicado');
});

it('recapturar un artículo en el conteo reemplaza la cantidad sin duplicar', function () {
    $gestor = app(GestionarConteo::class);
    $conteo = $gestor->crear('Prueba', $this->usuario);

    $gestor->capturar($conteo, $this->articulo, '4');
    $gestor->capturar($conteo, $this->articulo, '6');

    expect($conteo->partidas()->count())->toBe(1)->and($conteo->partidas()->first()->cantidad_contada)->toBe('6.000');
});

it('un conteo sin diferencias no genera movimientos', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Ajuste, '5');
    $antes = MovimientoInventario::count();
    $gestor = app(GestionarConteo::class);
    $conteo = $gestor->crear('Igual', $this->usuario);
    $gestor->capturar($conteo, $this->articulo, '5');

    expect($gestor->aplicar($conteo, $this->usuario))->toBe(['ajustados' => 0, 'sin_diferencia' => 1])
        ->and(MovimientoInventario::count())->toBe($antes);
});

it('no se puede aplicar ni capturar en un conteo ya aplicado', function () {
    $gestor = app(GestionarConteo::class);
    $conteo = $gestor->crear('Cerrado', $this->usuario);
    $gestor->capturar($conteo, $this->articulo, '1');
    $gestor->aplicar($conteo, $this->usuario);

    expect(fn () => $gestor->aplicar($conteo, $this->usuario))->toThrow(ValidationException::class)
        ->and(fn () => $gestor->capturar($conteo->refresh(), $this->articulo, '2'))->toThrow(ValidationException::class);
});

it('valida lo capturado en el conteo', function () {
    $gestor = app(GestionarConteo::class);
    $conteo = $gestor->crear('Validar', $this->usuario);

    expect(fn () => $gestor->capturar($conteo, $this->articulo, '-1'))->toThrow(ValidationException::class)
        ->and(fn () => $gestor->capturar($conteo, $this->articulo, 'abc'))->toThrow(ValidationException::class)
        ->and(fn () => $gestor->capturar($conteo, $this->articulo, '1.5'))->toThrow(ValidationException::class)
        ->and(fn () => $gestor->crear('   ', $this->usuario))->toThrow(ValidationException::class);
});

it('detecta artículos bajo el mínimo', function () {
    $bajo = Articulo::factory()->create(['existencia_minima' => '5']);
    ($this->movimiento)($bajo, TipoMovimiento::Ajuste, '3');
    $bien = Articulo::factory()->create(['existencia_minima' => '5']);
    ($this->movimiento)($bien, TipoMovimiento::Ajuste, '9');
    Articulo::factory()->create(); // sin mínimo definido

    expect(Articulo::bajoMinimo()->pluck('id')->all())->toBe([$bajo->id])
        ->and($bajo->refresh()->estaBajoMinimo())->toBeTrue()
        ->and($bien->refresh()->estaBajoMinimo())->toBeFalse();
});

it('al recapturar, el conteo se compara con la existencia de ese momento', function () {
    ($this->movimiento)($this->articulo, TipoMovimiento::Ajuste, '10');
    $gestor = app(GestionarConteo::class);
    $conteo = $gestor->crear('Recaptura', $this->usuario);

    $gestor->capturar($conteo, $this->articulo, '10');           // sistema 10
    ($this->movimiento)($this->articulo, TipoMovimiento::Venta, '-4'); // sistema 6
    $gestor->capturar($conteo, $this->articulo, '6');            // vuelve a contar: 6 en el estante

    $gestor->aplicar($conteo, $this->usuario);

    expect($this->articulo->refresh()->existencia)->toBe('6.000'); // sin ajuste fantasma de -4
});
