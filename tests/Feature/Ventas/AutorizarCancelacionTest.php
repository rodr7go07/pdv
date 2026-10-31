<?php

use App\Actions\Caja\CerrarTurno;
use App\Actions\Usuarios\VerificarAutorizacion;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create(['usuario' => 'jefa', 'password' => Hash::make('secreto-123')]);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create(['usuario' => 'encargado1', 'password' => Hash::make('clave-enc-9')]);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['usuario' => 'cajero1', 'password' => Hash::make('clave-caj-9')]);
    $this->otroCajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    $this->turno = abrirTurno($this->caja, $this->cajero);
    $this->articulo = Articulo::factory()->create(['precio_1' => '50', 'existencia_minima' => '1']);
    Articulo::query()->whereKey($this->articulo->id)->update(['existencia' => '20']);

    $this->vender = fn (?User $quien = null, ?Turno $turno = null): Venta => app(RegistrarVenta::class)(
        ($turno ?? $this->turno)->caja, $quien ?? $this->cajero,
        [['articulo_id' => $this->articulo->id, 'cantidad' => '2']],
        [['forma' => 'efectivo', 'monto' => '100']],
    );
});

// ─── Quién puede autorizar ───────────────────────────────────────────────────

it('el permiso de autorizar cancelaciones es del administrador y el encargado, no del cajero', function () {
    expect($this->admin->can('ventas.autorizar_cancelacion'))->toBeTrue()
        ->and($this->encargado->can('ventas.autorizar_cancelacion'))->toBeTrue()
        ->and($this->cajero->can('ventas.autorizar_cancelacion'))->toBeFalse()
        ->and($this->cajero->can('ventas.cancelar'))->toBeFalse();
});

it('un cajero cancela su venta con el turno abierto cuando un supervisor la autoriza', function () {
    $venta = ($this->vender)();

    $r = app(CancelarVenta::class)($venta, 'Se equivocó de producto', $this->cajero, $this->encargado);

    expect($r->estado)->toBe('cancelada')->and($r->cancelada_por)->toBe($this->cajero->id)->and($r->autorizada_por)->toBe($this->encargado->id)
        ->and($this->articulo->refresh()->existencia)->toBe('20.000');
});

it('un cajero no cancela sin autorización, con un autorizador sin permiso, ni ventas ajenas', function () {
    $venta = ($this->vender)();

    expect(fn () => app(CancelarVenta::class)($venta, 'x', $this->cajero))->toThrow(HttpException::class)
        ->and(fn () => app(CancelarVenta::class)($venta, 'x', $this->cajero, $this->otroCajero))->toThrow(HttpException::class)
        ->and(fn () => app(CancelarVenta::class)($venta, 'x', $this->otroCajero, $this->admin))->toThrow(HttpException::class);

    $inactivo = User::factory()->conRol(Rol::Encargado)->inactivo()->create();
    expect(fn () => app(CancelarVenta::class)($venta, 'x', $this->cajero, $inactivo))->toThrow(HttpException::class);
    expect($venta->refresh()->estado)->toBe('completada');
});

it('quien cancela directo queda anotado como quien autorizó', function () {
    $r = app(CancelarVenta::class)(($this->vender)(), 'Error de captura', $this->admin);

    expect($r->cancelada_por)->toBe($this->admin->id)->and($r->autorizada_por)->toBe($this->admin->id);
});

// ─── Verificación con usuario y contraseña ───────────────────────────────────

it('verifica al supervisor con su usuario y contraseña', function () {
    $verificar = app(VerificarAutorizacion::class);

    expect($verificar(' Encargado1 ', 'clave-enc-9', 'ventas.autorizar_cancelacion', $this->cajero)->id)->toBe($this->encargado->id);
});

it('rechaza usuario o contraseña equivocados, usuarios inactivos y quien no tiene el permiso, con el mismo mensaje', function () {
    $verificar = app(VerificarAutorizacion::class);
    $inactivo = User::factory()->conRol(Rol::Encargado)->inactivo()->create(['usuario' => 'baja', 'password' => Hash::make('clave-baja-9')]);

    $mensajes = collect([
        ['encargado1', 'mala'], ['nadie', 'clave-enc-9'], ['baja', 'clave-baja-9'], ['cajero1', 'clave-caj-9'],
    ])->map(function (array $datos) use ($verificar) {
        RateLimiter::clear('autorizacion:'.$this->cajero->id);

        try {
            $verificar($datos[0], $datos[1], 'ventas.autorizar_cancelacion', $this->cajero);
        } catch (ValidationException $e) {
            return $e->errors()['autorizacion'][0];
        }

        return null;
    });

    expect($mensajes->unique()->all())->toHaveCount(1)->and($mensajes->first())->toContain('No se pudo autorizar');
});

it('limita los intentos fallidos de autorización y se libera al acertar', function () {
    RateLimiter::clear('autorizacion:'.$this->cajero->id);
    $verificar = app(VerificarAutorizacion::class);

    foreach (range(1, VerificarAutorizacion::INTENTOS) as $i) {
        expect(fn () => $verificar('encargado1', 'mala', 'ventas.autorizar_cancelacion', $this->cajero))->toThrow(ValidationException::class, 'No se pudo autorizar');
    }

    // Ya bloqueado: ni siquiera con la contraseña correcta.
    expect(fn () => $verificar('encargado1', 'clave-enc-9', 'ventas.autorizar_cancelacion', $this->cajero))->toThrow(ValidationException::class, 'Demasiados intentos');

    RateLimiter::clear('autorizacion:'.$this->cajero->id);
    expect($verificar('encargado1', 'clave-enc-9', 'ventas.autorizar_cancelacion', $this->cajero)->id)->toBe($this->encargado->id);
});

// ─── Pantalla ────────────────────────────────────────────────────────────────

it('el cajero ve «Pedir autorización» en su venta y la cancela con la contraseña del supervisor', function () {
    $venta = ($this->vender)();

    $pantalla = Volt::actingAs($this->cajero)->test('pages.ventas.ver', ['venta' => $venta])
        ->assertSee('Pedir autorización para cancelar')->assertSee('Contraseña del supervisor')
        ->set('motivo', 'Cliente se arrepintió')->set('supervisor', 'encargado1')->set('claveSupervisor', 'mala')
        ->call('cancelar')->assertHasErrors('autorizacion')->assertSet('claveSupervisor', '');

    expect($venta->refresh()->estado)->toBe('completada');

    $pantalla->set('claveSupervisor', 'clave-enc-9')->call('cancelar')->assertHasNoErrors()
        ->assertSet('supervisor', '')->assertSet('claveSupervisor', '')->assertSee('Autorizó: '.$this->encargado->name);

    expect($venta->refresh()->estado)->toBe('cancelada')->and($venta->autorizada_por)->toBe($this->encargado->id);
});

it('el cajero no puede pedir autorización de una venta ajena ni de un turno cerrado', function () {
    $ajena = ($this->vender)();
    Volt::actingAs($this->otroCajero)->test('pages.ventas.ver', ['venta' => $ajena])->assertForbidden();

    app(CerrarTurno::class)->cerrar($this->turno, '100', $this->cajero);
    Volt::actingAs($this->cajero)->test('pages.ventas.ver', ['venta' => $ajena])
        ->assertDontSee('Pedir autorización para cancelar')
        ->set('motivo', 'x')->set('supervisor', 'encargado1')->set('claveSupervisor', 'clave-enc-9')
        ->call('cancelar')->assertForbidden();
});

it('el administrador cancela desde el detalle de la venta y ve cuándo se cancelaría con devolución total', function () {
    $venta = ($this->vender)();

    Volt::actingAs($this->admin)->test('pages.ventas.ver', ['venta' => $venta])
        ->assertSee('Cancelar venta')->assertDontSee('Contraseña del supervisor')
        ->set('motivo', 'Duplicada')->call('cancelar')->assertHasNoErrors()->assertSee('Venta cancelada');

    expect($venta->refresh()->estado)->toBe('cancelada');
});

it('una cancelación con el turno cerrado se muestra como devolución total y en la lista de ventas', function () {
    $venta = ($this->vender)();
    app(CerrarTurno::class)->cerrar($this->turno, '100', $this->cajero);
    abrirTurno(Caja::factory()->create(['serie' => 'Z']), $this->admin, '100');

    Volt::actingAs($this->admin)->test('pages.ventas.ver', ['venta' => $venta])
        ->assertSee('devolución total')
        ->set('motivo', 'Reclamo del cliente')->call('cancelar')->assertHasNoErrors()
        ->assertSee('Cancelada con devolución total')->assertSee('Reclamo del cliente');

    Volt::actingAs($this->admin)->test('pages.ventas.index')->assertSee('Cancelada (devuelta)');
});
