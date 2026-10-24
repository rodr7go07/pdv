<?php

use App\Actions\Clientes\RegistrarAbono;
use App\Actions\Clientes\RegistrarMovimientoCliente;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\ResumenTurno;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->turno = abrirTurno($this->caja, $this->cajero);
    $this->cajaEnc = Caja::factory()->create(['serie' => 'E']);
    $this->turnoEnc = abrirTurno($this->cajaEnc, $this->encargado);
    $this->articulo = Articulo::factory()->create(['precio_1' => '100', 'precio_2' => '80', 'precio_3' => '70', 'costo' => '40']);
    $this->cliente = Cliente::factory()->conCredito('500')->create(['nombre' => 'Doña Mary', 'lista_precios' => 2]);
});

function vender(array $pagos, string $cantidad = '2', ?Cliente $cliente = null, ?User $usuario = null): Venta
{
    $usuario ??= test()->cajero;

    return app(RegistrarVenta::class)(
        Turno::abiertoDe($usuario)->caja, $usuario,
        [['articulo_id' => test()->articulo->id, 'cantidad' => $cantidad]], $pagos, [], 1, $cliente,
    );
}

// ─── Lista de precios del cliente ────────────────────────────────────────────

it('usa la lista de precios del cliente', function () {
    $v = vender([['forma' => 'efectivo', 'monto' => '500']], '2', $this->cliente); // lista 2 → 80 × 2

    expect($v->total)->toBe('160.00')->and($v->lista_precios)->toBe(2)->and($v->cliente_id)->toBe($this->cliente->id);
});

it('sin cliente se usa la lista elegida y la del cliente manda sobre ella', function () {
    $normal = vender([['forma' => 'efectivo', 'monto' => '500']]);
    expect($normal->total)->toBe('200.00')->and($normal->cliente_id)->toBeNull();

    $this->cliente->update(['lista_precios' => 3]);
    $v = app(RegistrarVenta::class)($this->caja, $this->cajero, [['articulo_id' => $this->articulo->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '100']], [], 1, $this->cliente);
    expect($v->total)->toBe('70.00');
});

it('no se vende a un cliente inactivo', function () {
    $this->cliente->update(['activo' => false]);

    expect(fn () => vender([['forma' => 'efectivo', 'monto' => '500']], '1', $this->cliente))->toThrow(ValidationException::class, 'cliente');
});

// ─── Venta a crédito ─────────────────────────────────────────────────────────

it('vende a crédito, sube la deuda y guarda el movimiento', function () {
    $v = vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    $m = ClienteMovimiento::first();
    expect($v->pagos->pluck('monto', 'forma')->all())->toBe(['credito' => '160.00'])
        ->and($v->cambio)->toBe('0.00')
        ->and($this->cliente->refresh()->saldo)->toBe('160.00')
        ->and($m->tipo)->toBe('cargo')->and($m->saldo_resultante)->toBe('160.00')->and($m->venta_id)->toBe($v->id)->and($m->turno_id)->toBe($this->turno->id);
});

it('permite pago mixto: parte a crédito y el resto en efectivo con cambio', function () {
    $v = vender([['forma' => 'credito', 'monto' => '100'], ['forma' => 'efectivo', 'monto' => '100']], '2', $this->cliente); // total 160

    expect($v->pagos->pluck('monto', 'forma')->all())->toBe(['efectivo' => '60.00', 'credito' => '100.00'])
        ->and($v->cambio)->toBe('40.00')
        ->and($this->cliente->refresh()->saldo)->toBe('100.00');
});

it('rechaza el crédito sin cliente, sin límite, o más allá del disponible', function () {
    $pago = fn ($m) => [['forma' => 'credito', 'monto' => $m]];

    expect(fn () => vender($pago('200'), '2'))->toThrow(ValidationException::class, 'elige primero al cliente');

    $sinCredito = Cliente::factory()->create();
    expect(fn () => vender($pago('100'), '1', $sinCredito))->toThrow(ValidationException::class, 'no tiene crédito autorizado');

    expect(fn () => vender($pago('800'), '10', $this->cliente))->toThrow(ValidationException::class, 'Excede el crédito disponible');

    expect(Venta::count())->toBe(0)->and($this->cliente->refresh()->saldo)->toBe('0.00');
});

it('el límite considera lo que ya debe', function () {
    vender([['forma' => 'credito', 'monto' => '400']], '5', $this->cliente);   // 5 × 80 = 400 → debe 400, quedan 100

    expect(fn () => vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente))->toThrow(ValidationException::class, 'Excede el crédito disponible');

    vender([['forma' => 'credito', 'monto' => '80']], '1', $this->cliente);
    expect($this->cliente->refresh()->saldo)->toBe('480.00')->and($this->cliente->disponible())->toBe('20.00');
});

it('no se puede vender solo a crédito por un monto mayor al total', function () {
    expect(fn () => vender([['forma' => 'credito', 'monto' => '999']], '1', $this->cliente))->toThrow(ValidationException::class);
});

// ─── Abonos ──────────────────────────────────────────────────────────────────

it('registra un abono en efectivo: baja la deuda y entra al cajón del turno', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    $m = app(RegistrarAbono::class)($this->cliente, '60', 'efectivo', null, $this->cajero);

    expect($this->cliente->refresh()->saldo)->toBe('100.00')
        ->and($m->tipo)->toBe('abono')->and($m->saldo_resultante)->toBe('100.00')->and($m->turno_id)->toBe($this->turno->id)->and($m->forma)->toBe('efectivo');

    $r = ResumenTurno::calcular($this->turno);
    expect($r['abonos']['efectivo'])->toBe('60.00')->and($r['efectivo_esperado'])->toBe('60.00')->and($r['pagos']['credito'])->toBe('160.00');
});

it('un abono con tarjeta no entra al efectivo del cajón', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);
    app(RegistrarAbono::class)($this->cliente, '60', 'tarjeta', 'AUT9', $this->cajero);

    expect(ResumenTurno::calcular($this->turno)['efectivo_esperado'])->toBe('0.00');
});

it('el abono en efectivo exige turno abierto; con tarjeta no', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);
    $sinTurno = User::factory()->conRol(Rol::Cajero)->create();

    expect(fn () => app(RegistrarAbono::class)($this->cliente, '10', 'efectivo', null, $sinTurno))->toThrow(ValidationException::class, 'turno de caja abierto');

    app(RegistrarAbono::class)($this->cliente, '10', 'transferencia', null, $sinTurno);
    expect($this->cliente->refresh()->saldo)->toBe('150.00');
});

it('valida el abono: mayor que cero, no más que la deuda y forma válida', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);
    $abonar = app(RegistrarAbono::class);

    expect(fn () => $abonar($this->cliente, '0', 'efectivo', null, $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $abonar($this->cliente, '-5', 'efectivo', null, $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $abonar($this->cliente, 'abc', 'efectivo', null, $this->cajero))->toThrow(ValidationException::class)
        ->and(fn () => $abonar($this->cliente, '161', 'efectivo', null, $this->cajero))->toThrow(ValidationException::class, 'mayor que la deuda')
        ->and(fn () => $abonar($this->cliente, '10', 'cheque', null, $this->cajero))->toThrow(ValidationException::class);

    $abonar($this->cliente, '160', 'efectivo', null, $this->cajero);
    expect($this->cliente->refresh()->saldo)->toBe('0.00');
});

it('el saldo nunca queda negativo ni se cambia fuera de un movimiento', function () {
    expect(fn () => app(RegistrarMovimientoCliente::class)($this->cliente, 'abono', '10'))->toThrow(ValidationException::class);

    $this->cliente->fill(['saldo' => '999'])->save();
    expect($this->cliente->refresh()->saldo)->toBe('0.00');
});

// ─── Cancelación y devolución con crédito ────────────────────────────────────

it('cancelar una venta a crédito baja la deuda', function () {
    $v = vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    app(CancelarVenta::class)($v, 'Error', $this->encargado);

    $m = ClienteMovimiento::latest('id')->first();
    expect($this->cliente->refresh()->saldo)->toBe('0.00')->and($m->tipo)->toBe('cancelacion');
});

it('si el cliente ya abonó, la cancelación solo baja lo que todavía debe', function () {
    $v = vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);
    app(RegistrarAbono::class)($this->cliente, '100', 'transferencia', null, $this->cajero); // debe 60

    app(CancelarVenta::class)($v, 'Error', $this->encargado);

    expect($this->cliente->refresh()->saldo)->toBe('0.00')->and(ClienteMovimiento::latest('id')->first()->monto)->toBe('60.00');
});

it('una devolución de una venta a crédito baja la deuda y no saca efectivo del cajón', function () {
    $v = vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    $d = app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '1']], 'Dañado', $this->encargado);

    expect($d->total)->toBe('80.00')->and($d->monto_credito)->toBe('80.00')
        ->and($this->cliente->refresh()->saldo)->toBe('80.00')
        ->and(ResumenTurno::calcular($this->turnoEnc)['efectivo_esperado'])->toBe('0.00');
});

it('en una venta mixta la devolución se reparte entre deuda y efectivo', function () {
    $v = vender([['forma' => 'credito', 'monto' => '100'], ['forma' => 'efectivo', 'monto' => '60']], '2', $this->cliente); // 160: 100 crédito + 60 efectivo

    $d = app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '2']], 'Todo', $this->encargado);

    // Se devuelven 160: 100 a la deuda (lo que se fió) y 60 en efectivo
    expect($d->total)->toBe('160.00')->and($d->monto_credito)->toBe('100.00')
        ->and($this->cliente->refresh()->saldo)->toBe('0.00')
        ->and(ResumenTurno::calcular($this->turnoEnc)['efectivo_esperado'])->toBe('-60.00');
});

it('la parte a crédito de una devolución no pasa de lo que el cliente todavía debe', function () {
    $v = vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);
    app(RegistrarAbono::class)($this->cliente, '150', 'transferencia', null, $this->cajero); // debe 10

    $d = app(RegistrarDevolucion::class)($v, [['partida_id' => $v->partidas[0]->id, 'cantidad' => '1']], 'x', $this->encargado);

    expect($d->monto_credito)->toBe('10.00')->and($this->cliente->refresh()->saldo)->toBe('0.00')
        ->and(ResumenTurno::calcular($this->turnoEnc)['efectivo_esperado'])->toBe('-70.00');
});

// ─── Pantallas ───────────────────────────────────────────────────────────────

it('el cajero ve clientes pero no puede crear ni cambiar su crédito', function () {
    $this->actingAs($this->cajero)->get('/clientes')->assertOk()->assertSee('Doña Mary')->assertDontSee('Nuevo cliente');

    Volt::actingAs($this->cajero)->test('pages.clientes.index')->call('nuevo')->assertForbidden();
});

it('el encargado crea clientes y el administrador fija crédito y lista de precios', function () {
    $sinCredito = User::factory()->create();
    $sinCredito->givePermissionTo(['clientes.ver', 'clientes.administrar']);

    Volt::actingAs($sinCredito)->test('pages.clientes.index')
        ->call('nuevo')->set('nombre', 'Juan Pérez')->set('limite_credito', '9999')->set('lista_precios', '3')->call('guardar')->assertHasNoErrors();

    $c = Cliente::where('nombre', 'Juan Pérez')->first();
    expect($c->limite_credito)->toBe('0.00')->and($c->lista_precios)->toBe(1); // sin permiso de crédito, se ignoran

    Volt::actingAs($this->admin)->test('pages.clientes.index')
        ->call('editar', $c->id)->set('limite_credito', '1500')->set('lista_precios', '3')->call('guardar')->assertHasNoErrors();

    expect($c->refresh()->limite_credito)->toBe('1500.00')->and($c->lista_precios)->toBe(3);
});

it('valida los datos del cliente', function () {
    Volt::actingAs($this->admin)->test('pages.clientes.index')
        ->call('nuevo')->set('nombre', '')->set('correo', 'no-es-correo')->set('rfc', 'xx')->set('limite_credito', '-5')->set('lista_precios', '9')
        ->call('guardar')->assertHasErrors(['nombre', 'correo', 'rfc', 'limite_credito', 'lista_precios']);
});

it('filtra clientes con deuda y muestra el resumen', function () {
    Cliente::factory()->create(['nombre' => 'Sin Deuda']);
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    Volt::actingAs($this->admin)->test('pages.clientes.index')
        ->assertSee('Sin Deuda')->assertSee('Doña Mary')->assertSee('$160.00')
        ->set('filtro', 'saldo')->assertSee('Doña Mary')->assertDontSee('Sin Deuda');
});

it('registra un abono desde el estado de cuenta', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    Volt::actingAs($this->cajero)->test('pages.clientes.cuenta', ['cliente' => $this->cliente])
        ->assertSee('Compra a crédito')
        ->set('monto', '60')->set('forma', 'efectivo')->call('abonar')->assertHasNoErrors()
        ->assertSee('Abono de $60.00 registrado');

    expect($this->cliente->refresh()->saldo)->toBe('100.00');
});

it('el abono desde la pantalla muestra el error si excede la deuda', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    Volt::actingAs($this->cajero)->test('pages.clientes.cuenta', ['cliente' => $this->cliente])
        ->set('monto', '500')->call('abonar')->assertHasErrors('monto');
});

it('imprime el estado de cuenta', function () {
    vender([['forma' => 'credito', 'monto' => '160']], '2', $this->cliente);

    $this->actingAs($this->cajero)->get("/clientes/{$this->cliente->id}/estado-de-cuenta")
        ->assertOk()->assertSee('ESTADO DE CUENTA')->assertSee('Doña Mary')->assertSee('$160.00')->assertSee('A-000001');
});

it('los clientes exigen permiso de consulta', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->get('/clientes')->assertForbidden();
    $this->actingAs($sinPermiso)->get("/clientes/{$this->cliente->id}")->assertForbidden();
    $this->actingAs($sinPermiso)->get("/clientes/{$this->cliente->id}/estado-de-cuenta")->assertForbidden();
});

it('los visitantes no ven clientes', function () {
    $this->get('/clientes')->assertRedirect('/login');
});

it('la caja busca clientes por nombre o teléfono y vende con su crédito', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    $r = $c->buscarClientes('mary');
    expect($r)->toHaveCount(1)->and($r[0])->toMatchArray(['nombre' => 'Doña Mary', 'lista' => 2, 'credito' => true, 'disponible' => '500.00'])
        ->and($c->buscarClientes('m'))->toBe([]);

    $p = $c->previsualizar([['articulo_id' => $this->articulo->id, 'cantidad' => '2']], [], 1, $this->cliente->id);
    expect($p)->toMatchArray(['ok' => true, 'total' => '160.00', 'disponible' => '500.00']);

    $v = $c->cobrar([['articulo_id' => $this->articulo->id, 'cantidad' => '2']], [['forma' => 'credito', 'monto' => '160']], [], 1, $this->cliente->id);
    expect($v['ok'])->toBeTrue()->and($this->cliente->refresh()->saldo)->toBe('160.00');
});

it('la caja rechaza clientes inexistentes o inactivos', function () {
    $this->cliente->update(['activo' => false]);
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    expect($c->previsualizar([['articulo_id' => $this->articulo->id, 'cantidad' => '1']], [], 1, $this->cliente->id)['ok'])->toBeFalse()
        ->and($c->buscarClientes('mary'))->toBe([]);
});
