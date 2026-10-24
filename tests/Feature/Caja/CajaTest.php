<?php

use App\Actions\Caja\AbrirTurno;
use App\Actions\Caja\CerrarTurno;
use App\Actions\Caja\RegistrarMovimientoCaja;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Corte;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\ResumenTurno;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja = Caja::factory()->create(['serie' => 'A', 'nombre' => 'Caja 1']);
    $this->caja2 = Caja::factory()->create(['serie' => 'B', 'nombre' => 'Caja 2']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Cajero Uno']);
    $this->otro = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Cajero Dos']);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->articulo = Articulo::factory()->create(['precio_1' => '50', 'costo' => '30']);
    $this->abrir = app(AbrirTurno::class);
});

function venta(Turno $turno, string $cantidad, array $pagos): Venta
{
    return app(RegistrarVenta::class)($turno->caja, $turno->usuario, [['articulo_id' => Articulo::first()->id, 'cantidad' => $cantidad]], $pagos);
}

// ─── Apertura ────────────────────────────────────────────────────────────────

it('abre un turno con fondo inicial', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '500');

    expect($t->estado)->toBe('abierto')->and($t->fondo_inicial)->toBe('500.00')->and($t->user_id)->toBe($this->cajero->id)
        ->and(Turno::abiertoDe($this->cajero)->id)->toBe($t->id);
});

it('el fondo vacío cuenta como cero y se valida', function () {
    expect((($this->abrir)($this->caja, $this->cajero, ''))->fondo_inicial)->toBe('0.00');

    foreach (['-5', 'abc', '999999999'] as $malo) {
        expect(fn () => ($this->abrir)($this->caja2, $this->otro, $malo))->toThrow(ValidationException::class);
    }
});

it('solo un turno abierto por caja y por usuario', function () {
    ($this->abrir)($this->caja, $this->cajero, '0');

    expect(fn () => ($this->abrir)($this->caja, $this->otro, '0'))->toThrow(ValidationException::class)       // caja ocupada
        ->and(fn () => ($this->abrir)($this->caja2, $this->cajero, '0'))->toThrow(ValidationException::class); // usuario ya tiene turno

    expect(Turno::abiertos()->count())->toBe(1);
});

it('la base de datos también lo garantiza', function () {
    ($this->abrir)($this->caja, $this->cajero, '0');

    expect(fn () => Turno::query()->create([
        'caja_id' => $this->caja->id, 'user_id' => $this->otro->id, 'estado' => 'abierto', 'fondo_inicial' => 0,
        'abierto_at' => now(), 'caja_abierta_id' => $this->caja->id, 'usuario_abierto_id' => $this->otro->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('no abre turno en una caja desactivada ni sin permiso', function () {
    $this->caja->update(['activa' => false]);
    expect(fn () => ($this->abrir)($this->caja, $this->cajero, '0'))->toThrow(ValidationException::class);

    $sinPermiso = User::factory()->create();
    expect(fn () => ($this->abrir)($this->caja2, $sinPermiso, '0'))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// ─── Venta exige turno ───────────────────────────────────────────────────────

it('no se vende sin turno abierto', function () {
    expect(fn () => app(RegistrarVenta::class)($this->caja, $this->cajero, [['articulo_id' => $this->articulo->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '50']]))
        ->toThrow(ValidationException::class, 'Abre tu turno');

    expect(Venta::count())->toBe(0);
});

it('no se vende con el turno de otro cajero ni en otra caja', function () {
    ($this->abrir)($this->caja, $this->cajero, '0');
    $v = fn ($caja, $user) => app(RegistrarVenta::class)($caja, $user, [['articulo_id' => $this->articulo->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '50']]);

    expect(fn () => $v($this->caja, $this->otro))->toThrow(ValidationException::class)
        ->and(fn () => $v($this->caja2, $this->cajero))->toThrow(ValidationException::class);
});

it('la venta queda ligada a su turno', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');

    expect(venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']])->turno_id)->toBe($t->id);
});

it('no se vende en un turno cerrado', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    app(CerrarTurno::class)->cerrar($t, '0', $this->cajero);

    expect(fn () => venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]))->toThrow(ValidationException::class);
});

// ─── Entradas y retiros ──────────────────────────────────────────────────────

it('registra entradas y retiros con motivo', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    $mov = app(RegistrarMovimientoCaja::class);

    $mov($t, 'entrada', '50', 'Cambio del banco', $this->encargado);
    $mov($t, 'retiro', '120.50', 'Pago a proveedor', $this->encargado);

    $r = ResumenTurno::calcular($t);
    expect($r['entradas'])->toBe('50.00')->and($r['retiros'])->toBe('120.50')->and($r['efectivo_esperado'])->toBe('29.50');
});

it('no permite retirar más de lo que hay en el cajón', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');

    expect(fn () => app(RegistrarMovimientoCaja::class)($t, 'retiro', '100.01', 'Mucho', $this->encargado))->toThrow(ValidationException::class);

    app(RegistrarMovimientoCaja::class)($t, 'retiro', '100', 'Todo', $this->encargado);
    expect(ResumenTurno::calcular($t)['efectivo_esperado'])->toBe('0.00');
});

it('valida monto, motivo y tipo del movimiento', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    $mov = app(RegistrarMovimientoCaja::class);

    expect(fn () => $mov($t, 'entrada', '0', 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $mov($t, 'entrada', '-5', 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $mov($t, 'entrada', 'abc', 'x', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $mov($t, 'entrada', '5', '   ', $this->encargado))->toThrow(ValidationException::class)
        ->and(fn () => $mov($t, 'regalo', '5', 'x', $this->encargado))->toThrow(ValidationException::class);
});

it('el cajero no mueve efectivo del turno de otro; el encargado sí', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');

    // El cajero normal tiene caja.retiro? No: solo caja.abrir y caja.corte
    expect(fn () => app(RegistrarMovimientoCaja::class)($t, 'retiro', '10', 'x', $this->cajero))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    app(RegistrarMovimientoCaja::class)($t, 'retiro', '10', 'Supervisor', $this->encargado);
    expect(ResumenTurno::calcular($t)['retiros'])->toBe('10.00');
});

it('no se mueve efectivo en un turno cerrado', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    app(CerrarTurno::class)->cerrar($t, '100', $this->cajero);

    expect(fn () => app(RegistrarMovimientoCaja::class)($t, 'entrada', '10', 'x', $this->encargado))->toThrow(ValidationException::class);
});

// ─── Resumen y corte ─────────────────────────────────────────────────────────

it('el efectivo esperado suma fondo, ventas en efectivo y entradas y resta retiros y devoluciones', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '200');

    venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]);                                       // 100 efectivo
    venta($t, '3', [['forma' => 'tarjeta', 'monto' => '100'], ['forma' => 'efectivo', 'monto' => '50']]); // 150: 100 tarjeta + 50 efectivo
    venta($t, '1', [['forma' => 'transferencia', 'monto' => '50']]);                                    // 50 transferencia
    app(RegistrarMovimientoCaja::class)($t, 'entrada', '30', 'Cambio', $this->encargado);
    app(RegistrarMovimientoCaja::class)($t, 'retiro', '80', 'Depósito', $this->encargado);

    $r = ResumenTurno::calcular($t);

    expect($r['ventas'])->toMatchArray(['cantidad' => 3, 'total' => '300.00'])
        ->and($r['pagos'])->toBe(['efectivo' => '150.00', 'tarjeta' => '100.00', 'transferencia' => '50.00', 'credito' => '0.00'])
        ->and($r['efectivo_esperado'])->toBe('300.00'); // 200 + 150 + 30 − 80
});

it('las ventas canceladas no cuentan en el corte', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $v1 = venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]);
    venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]);

    app(CancelarVenta::class)($v1, 'Error', $this->encargado);

    $r = ResumenTurno::calcular($t);
    expect($r['ventas']['cantidad'])->toBe(1)->and($r['canceladas'])->toBe(['cantidad' => 1, 'total' => '100.00'])->and($r['efectivo_esperado'])->toBe('50.00');
});

it('las devoluciones salen del cajón del turno de quien las procesa', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $turnoEncargado = ($this->abrir)($this->caja2, $this->encargado, '0');
    $v = venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]);

    app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '1']], 'Dañado', $this->encargado);

    expect(ResumenTurno::calcular($turnoEncargado))->toMatchArray(['efectivo_esperado' => '-50.00'])
        ->and(ResumenTurno::calcular($t)['efectivo_esperado'])->toBe('100.00');
});

it('sin turno propio, la devolución usa el turno abierto de la venta', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $v = venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]);

    $d = app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '1']], 'Dañado', $this->admin);

    expect($d->turno_id)->toBe($t->id)->and(ResumenTurno::calcular($t)['efectivo_esperado'])->toBe('50.00');
});

it('no se devuelve dinero sin ningún turno abierto', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $v = venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]);
    app(CerrarTurno::class)->cerrar($t, '100', $this->cajero);

    expect(fn () => app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '1']], 'x', $this->admin))
        ->toThrow(ValidationException::class, 'turno de caja abierto');
});

it('no se cancela una venta de un turno ya cerrado', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $v = venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]);
    app(CerrarTurno::class)->cerrar($t, '50', $this->cajero);

    expect(fn () => app(CancelarVenta::class)($v, 'Tarde', $this->admin))->toThrow(ValidationException::class, 'devolución');
});

// ─── Cortes ──────────────────────────────────────────────────────────────────

it('el corte X guarda las cifras sin cerrar el turno', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]);

    $x = app(CerrarTurno::class)->corteX($t, $this->cajero);

    expect($x->tipo)->toBe('X')->and($x->datos['efectivo_esperado'])->toBe('150.00')->and($x->datos['caja'])->toBe('Caja 1')
        ->and($t->refresh()->estaAbierto())->toBeTrue();

    // Una venta posterior no cambia la foto del corte anterior
    venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]);
    expect($x->refresh()->datos['efectivo_esperado'])->toBe('150.00');
});

it('el corte Z cierra el turno y calcula el sobrante o faltante', function (string $contado, string $diferencia) {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    venta($t, '2', [['forma' => 'efectivo', 'monto' => '100']]); // esperado 200

    $z = app(CerrarTurno::class)->cerrar($t, $contado, $this->cajero, 'Todo bien');

    $t->refresh();
    expect($z->tipo)->toBe('Z')->and($t->estado)->toBe('cerrado')->and($t->efectivo_esperado)->toBe('200.00')
        ->and($t->efectivo_contado)->toBe(number_format((float) $contado, 2, '.', ''))->and($t->diferencia)->toBe($diferencia)
        ->and($t->caja_abierta_id)->toBeNull()->and($t->usuario_abierto_id)->toBeNull()->and($t->cerrado_por)->toBe($this->cajero->id)
        ->and($z->datos['diferencia'])->toBe($diferencia)->and($z->datos['notas'])->toBe('Todo bien');
})->with([
    'cuadrada' => ['200', '0.00'],
    'faltante' => ['195.50', '-4.50'],
    'sobrante' => ['203', '3.00'],
]);

it('al cerrar se libera la caja y el usuario para otro turno', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    app(CerrarTurno::class)->cerrar($t, '0', $this->cajero);

    $nuevo = ($this->abrir)($this->caja, $this->otro, '10');
    $propio = ($this->abrir)($this->caja2, $this->cajero, '10');

    expect($nuevo->estaAbierto())->toBeTrue()->and($propio->estaAbierto())->toBeTrue();
});

it('no se cierra dos veces ni con un arqueo inválido', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    $cerrar = app(CerrarTurno::class);

    expect(fn () => $cerrar->cerrar($t, '', $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $cerrar->cerrar($t, '-1', $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $cerrar->cerrar($t, 'abc', $this->cajero))->toThrow(ValidationException::class);

    $cerrar->cerrar($t, '0', $this->cajero);

    expect(fn () => $cerrar->cerrar($t->refresh(), '0', $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $cerrar->corteX($t, $this->cajero))->toThrow(ValidationException::class);
});

it('un cajero no cierra el turno de otro; el encargado sí', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');

    expect(fn () => app(CerrarTurno::class)->cerrar($t, '0', $this->otro))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    app(CerrarTurno::class)->cerrar($t, '0', $this->encargado);
    expect($t->refresh()->cerrado_por)->toBe($this->encargado->id);
});

// ─── Pantallas ───────────────────────────────────────────────────────────────

it('la pantalla Mi caja abre el turno y muestra las cifras', function () {
    Volt::actingAs($this->cajero)->test('pages.caja.index')
        ->set('cajaElegida', (string) $this->caja->id)
        ->set('fondo', '300')
        ->call('abrir')
        ->assertHasNoErrors()
        ->assertSee('Turno abierto')
        ->assertSee('$300.00');

    expect(Turno::abiertoDe($this->cajero))->not->toBeNull();
});

it('Mi caja avisa si la caja ya está ocupada o falta elegirla', function () {
    ($this->abrir)($this->caja, $this->otro, '0');

    Volt::actingAs($this->cajero)->test('pages.caja.index')
        ->call('abrir')->assertHasErrors('cajaElegida')
        ->set('cajaElegida', (string) $this->caja->id)->set('fondo', '0')->call('abrir')->assertHasErrors('cajaElegida');
});

it('el cajero no ve el efectivo esperado pero el encargado sí', function () {
    ($this->abrir)($this->caja, $this->cajero, '100');
    ($this->abrir)($this->caja2, $this->encargado, '100');

    Volt::actingAs($this->cajero)->test('pages.caja.index')->assertDontSee('Efectivo esperado');
    Volt::actingAs($this->encargado)->test('pages.caja.index')->assertSee('Efectivo esperado');
});

it('registra un retiro y el corte X desde la pantalla (encargado)', function () {
    ($this->abrir)($this->caja2, $this->encargado, '500');

    Volt::actingAs($this->encargado)->test('pages.caja.index')
        ->set('tipoMovimiento', 'retiro')->set('monto', '100')->set('motivo', 'Depósito')
        ->call('registrarMovimiento')->assertHasNoErrors()->assertSee('Retiro registrado')
        ->call('corteX')->assertSee('Corte parcial generado');

    expect(Corte::where('tipo', 'X')->count())->toBe(1);
});

it('cierra el turno desde la pantalla y deja el corte final', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');

    Volt::actingAs($this->cajero)->test('pages.caja.index')
        ->set('contado', '')->call('cerrarTurno')->assertHasErrors('contado')
        ->set('contado', '100')->call('cerrarTurno')->assertHasNoErrors()->assertSee('Turno cerrado');

    expect($t->refresh()->estado)->toBe('cerrado')->and(Corte::where('tipo', 'Z')->count())->toBe(1);
});

it('la lista de turnos es solo para supervisores', function () {
    ($this->abrir)($this->caja, $this->cajero, '0');

    $this->actingAs($this->cajero)->get('/caja/turnos')->assertForbidden();
    $this->actingAs($this->encargado)->get('/caja/turnos')->assertOk()->assertSee('Cajero Uno');
});

it('un cajero ve su propio turno pero no el de otro', function () {
    $mio = ($this->abrir)($this->caja, $this->cajero, '0');
    $ajeno = ($this->abrir)($this->caja2, $this->otro, '0');

    $this->actingAs($this->cajero)->get("/caja/turnos/{$mio->id}")->assertOk();
    $this->actingAs($this->cajero)->get("/caja/turnos/{$ajeno->id}")->assertForbidden();
    $this->actingAs($this->encargado)->get("/caja/turnos/{$ajeno->id}")->assertOk();
});

it('el supervisor cierra el turno de un cajero desde su detalle', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '50');

    Volt::actingAs($this->encargado)->test('pages.caja.turno', ['turno' => $t])
        ->set('contado', '50')->call('cerrar')->assertHasNoErrors()->assertSee('Turno cerrado');

    expect($t->refresh()->estado)->toBe('cerrado');
});

it('imprime el corte con el ancho de papel de la caja y con permisos', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '100');
    $this->caja->update(['ancho_papel' => 58]);
    $z = app(CerrarTurno::class)->cerrar($t, '100', $this->cajero);

    $this->actingAs($this->cajero)->get("/caja/cortes/{$z->id}")->assertOk()->assertSee('CORTE FINAL (Z)')->assertSee('CAJA CUADRADA')->assertSee('58mm', false);
    $this->actingAs($this->otro)->get("/caja/cortes/{$z->id}")->assertForbidden();
    $this->actingAs($this->encargado)->get("/caja/cortes/{$z->id}")->assertOk();
});

it('la administración de cajas es solo para quien tiene permiso', function () {
    $this->actingAs($this->cajero)->get('/cajas')->assertForbidden();
    $this->actingAs($this->encargado)->get('/cajas')->assertOk();
});

it('crea y edita cajas validando la serie', function () {
    $c = Volt::actingAs($this->admin)->test('pages.cajas')
        ->call('nueva')->set('nombre', 'Caja 3')->set('serie', 'c3')->set('ancho_papel', '58')->call('guardar')->assertHasNoErrors();

    $caja = Caja::where('serie', 'C3')->first();
    expect($caja)->not->toBeNull()->and($caja->ancho_papel)->toBe(58);

    $c->call('nueva')->set('nombre', 'Otra')->set('serie', 'A')->call('guardar')->assertHasErrors('serie')
        ->call('nueva')->set('nombre', 'Otra')->set('serie', 'ab cd')->call('guardar')->assertHasErrors('serie')
        ->call('nueva')->set('nombre', 'Caja 1')->set('serie', 'ZZ')->call('guardar')->assertHasErrors('nombre');
});

it('no cambia la serie de una caja con ventas ni desactiva una con turno abierto', function () {
    $t = ($this->abrir)($this->caja, $this->cajero, '0');
    venta($t, '1', [['forma' => 'efectivo', 'monto' => '50']]);

    Volt::actingAs($this->admin)->test('pages.cajas')
        ->call('editar', $this->caja->id)->set('serie', 'NUEVA')->call('guardar')->assertHasErrors('serie')
        ->call('editar', $this->caja->id)->set('activa', false)->call('guardar')->assertHasErrors('activa');
});
