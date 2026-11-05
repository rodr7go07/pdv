<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\EstadoImpresion;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Impresion;
use App\Models\User;
use App\Models\Venta;
use App\Services\ColaImpresion;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->otro = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    $this->cajaB = Caja::factory()->create(['serie' => 'B']);
    abrirTurno($this->caja, $this->cajero);
    abrirTurno($this->cajaB, $this->otro);
    $articulo = Articulo::factory()->create(['precio_1' => '20']);
    Articulo::query()->whereKey($articulo->id)->update(['existencia' => '50']);

    $this->vender = fn (User $quien, Caja $caja): Venta => app(RegistrarVenta::class)(
        $caja, $quien, [['articulo_id' => $articulo->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '20']],
    );
    $this->venta = ($this->vender)($this->cajero, $this->caja);
    $this->cola = app(ColaImpresion::class);
});

// ─── Cola ────────────────────────────────────────────────────────────────────

it('un pedido de impresión queda pendiente en la caja que hizo la venta y no se duplica', function () {
    $a = $this->cola->solicitar($this->venta, $this->cajero);
    $b = $this->cola->solicitar($this->venta, $this->encargado);

    expect($a->estado)->toBe(EstadoImpresion::Pendiente)->and($a->caja_id)->toBe($this->caja->id)->and($a->solicitada_por)->toBe($this->cajero->id)
        ->and($b->id)->toBe($a->id)->and(Impresion::count())->toBe(1);
});

it('un cajero no pide imprimir una venta ajena y una caja desactivada no recibe pedidos', function () {
    expect(fn () => $this->cola->solicitar($this->venta, $this->otro))->toThrow(HttpException::class);

    $this->caja->update(['activa' => false]);
    expect(fn () => $this->cola->solicitar($this->venta, $this->encargado))->toThrow(ValidationException::class, 'ya no está disponible');
});

it('la caja ve sus pedidos pendientes, del más viejo al más nuevo, y los viejos caducan', function () {
    $viejo = $this->cola->solicitar($this->venta, $this->cajero);
    $nueva = ($this->vender)($this->cajero, $this->caja);
    $nuevo = $this->cola->solicitar($nueva, $this->cajero);
    $ajeno = $this->cola->solicitar(($this->vender)($this->otro, $this->cajaB), $this->otro);

    expect($this->cola->pendientesDe($this->caja)->pluck('id')->all())->toBe([$viejo->id, $nuevo->id])
        ->and($this->cola->pendientesDe($this->cajaB)->pluck('id')->all())->toBe([$ajeno->id]);

    $this->travel(ColaImpresion::MINUTOS_VIGENCIA + 1)->minutes();

    expect($this->cola->pendientesDe($this->caja))->toBeEmpty()->and($viejo->fresh()->estado)->toBe(EstadoImpresion::Caducado);
});

it('solo una pantalla logra reservar el pedido', function () {
    $p = $this->cola->solicitar($this->venta, $this->cajero);

    expect($this->cola->reservar($p))->toBeTrue()->and($this->cola->reservar($p))->toBeFalse()
        ->and($p->fresh()->estado)->toBe(EstadoImpresion::Imprimiendo)->and($p->fresh()->intentos)->toBe(1);
});

it('al terminar queda impreso; si falla vuelve a la cola y tras tres intentos falla', function () {
    $p = $this->cola->solicitar($this->venta, $this->cajero);

    foreach ([1, 2] as $intento) {
        $this->cola->reservar($p);
        $this->cola->confirmar($p, false);
        expect($p->fresh()->estado)->toBe(EstadoImpresion::Pendiente)->and($p->fresh()->intentos)->toBe($intento);
    }

    $this->cola->reservar($p);
    $this->cola->confirmar($p, false);
    expect($p->fresh()->estado)->toBe(EstadoImpresion::Fallo);

    $otro = $this->cola->solicitar($this->venta, $this->cajero);
    $this->cola->reservar($otro);
    $this->cola->confirmar($otro, true);
    expect($otro->fresh()->estado)->toBe(EstadoImpresion::Impreso)->and($otro->fresh()->impreso_at)->not->toBeNull();
});

it('un pedido que se quedó imprimiendo se reintenta y después falla', function () {
    $p = $this->cola->solicitar($this->venta, $this->cajero);
    $this->cola->reservar($p);

    $this->travel(ColaImpresion::SEGUNDOS_IMPRIMIENDO + 5)->seconds();
    expect($this->cola->caducar())->toBe(1)->and($p->fresh()->estado)->toBe(EstadoImpresion::Pendiente);

    Impresion::whereKey($p->id)->update(['intentos' => ColaImpresion::MAX_INTENTOS, 'estado' => 'imprimiendo', 'updated_at' => now()->subMinutes(5)]);
    $this->cola->caducar();
    expect($p->fresh()->estado)->toBe(EstadoImpresion::Fallo);
});

it('se puede cancelar un pedido vigente pero no uno ya impreso, y el cron caduca los viejos', function () {
    $p = $this->cola->solicitar($this->venta, $this->cajero);
    expect($this->cola->cancelar($p))->toBeTrue()->and($p->fresh()->estado)->toBe(EstadoImpresion::Cancelado);

    $q = $this->cola->solicitar($this->venta, $this->cajero);
    $this->cola->reservar($q);
    $this->cola->confirmar($q, true);
    expect($this->cola->cancelar($q))->toBeFalse();

    $viejo = $this->cola->solicitar(($this->vender)($this->cajero, $this->caja), $this->cajero);
    Impresion::whereKey($viejo->id)->update(['created_at' => now()->subHour()]);
    $this->artisan('impresiones:caducar')->expectsOutputToContain('1')->assertSuccessful();
    expect($viejo->fresh()->estado)->toBe(EstadoImpresion::Caducado);
});

// ─── Pantalla de Venta (endpoints) ───────────────────────────────────────────

it('la pantalla de Venta de la caja consulta sus pedidos y el modo de la caja', function () {
    $p = $this->cola->solicitar($this->venta, $this->encargado);

    $r = $this->actingAs($this->cajero)->getJson('/venta/impresiones')->assertOk();
    expect($r->json('modo'))->toBe('sola')->and($r->json('pendientes'))->toBe([['id' => $p->id, 'folio' => $this->venta->folio]]);

    $this->caja->update(['impresion_pedidos' => 'aviso']);
    expect($this->actingAs($this->cajero)->getJson('/venta/impresiones')->json('modo'))->toBe('aviso');

    // La pantalla de otra caja no ve los pedidos de esta.
    expect($this->actingAs($this->otro)->getJson('/venta/impresiones')->json('pendientes'))->toBe([]);

    // Sin turno abierto no hay caja que atender.
    $sinTurno = User::factory()->conRol(Rol::Cajero)->create();
    expect($this->actingAs($sinTurno)->getJson('/venta/impresiones')->json('pendientes'))->toBe([]);
});

it('reservar devuelve la dirección firmada del ticket y una segunda pantalla recibe un conflicto', function () {
    $p = $this->cola->solicitar($this->venta, $this->encargado);

    $r = $this->actingAs($this->cajero)->postJson("/venta/impresiones/{$p->id}/reservar")->assertOk();
    expect($r->json('ok'))->toBeTrue()->and($r->json('folio'))->toBe($this->venta->folio)->and($r->json('url'))->toContain('signature=')->toStartWith('/venta/impresiones/');

    $this->actingAs($this->cajero)->postJson("/venta/impresiones/{$p->id}/reservar")->assertStatus(409);
    $this->actingAs($this->otro)->postJson("/venta/impresiones/{$p->id}/reservar")->assertForbidden();
});

it('el ticket del pedido solo se abre con la dirección firmada, aun siendo venta de otro cajero', function () {
    // La venta la hizo otro cajero en un turno anterior de esta caja: el cajero de hoy igual puede imprimirla.
    $this->venta->update(['user_id' => $this->otro->id]);
    $p = $this->cola->solicitar($this->venta, $this->encargado);
    $this->cola->reservar($p);

    $firmada = URL::temporarySignedRoute('impresiones.ticket', now()->addMinutes(3), ['impresion' => $p->id], absolute: false);

    $this->actingAs($this->cajero)->get($firmada)->assertOk()->assertSee($this->venta->folio)->assertSee('window.print()', false);
    $this->actingAs($this->cajero)->get("/venta/impresiones/{$p->id}/ticket")->assertForbidden();   // sin firma
    $this->actingAs($this->otro)->get($firmada)->assertForbidden();                                   // otra caja

    $this->cola->confirmar($p, true);
    $this->actingAs($this->cajero)->get($firmada)->assertStatus(410);                                  // ya no está en espera
});

it('la pantalla confirma que imprimió o descarta el pedido', function () {
    $p = $this->cola->solicitar($this->venta, $this->encargado);
    $this->cola->reservar($p);

    $this->actingAs($this->cajero)->postJson("/venta/impresiones/{$p->id}/confirmar", ['impreso' => true])->assertOk();
    expect($p->fresh()->estado)->toBe(EstadoImpresion::Impreso);

    $q = $this->cola->solicitar($this->venta, $this->encargado);
    $this->actingAs($this->cajero)->postJson("/venta/impresiones/{$q->id}/descartar")->assertOk()->assertJson(['ok' => true]);
    expect($q->fresh()->estado)->toBe(EstadoImpresion::Cancelado);
});

it('la pantalla de Venta trae las direcciones y los controles de la impresión pedida', function () {
    $this->actingAs($this->cajero)->get('/venta')->assertOk()
        ->assertSee('Llegó un ticket para imprimir', false)->assertSee('alternarImprimeAqui()', false)->assertSee('pedirImpresionEnCaja(exito.id)', false)
        ->assertSee('urlImpresiones', false);
});

it('desde la caja se puede pedir imprimir una venta propia, no una ajena', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    expect($c->pedirImpresion($this->venta->id))->toMatchArray(['ok' => true])
        ->and($c->pedirImpresion($this->venta->id)['mensaje'])->toContain('se imprimirá en la caja')
        ->and(Impresion::count())->toBe(1);

    $this->caja->update(['impresion_pedidos' => 'aviso']);
    Impresion::query()->delete();
    expect($c->pedirImpresion($this->venta->id)['mensaje'])->toContain('avisará');

    $ajena = ($this->vender)($this->otro, $this->cajaB);
    expect($c->pedirImpresion($ajena->id))->toMatchArray(['ok' => false]);
});

// ─── Detalle de la venta y configuración de la caja ──────────────────────────

it('el detalle de la venta pide imprimir en la caja, muestra el estado y permite cancelar', function () {
    $pantalla = Volt::actingAs($this->encargado)->test('pages.ventas.ver', ['venta' => $this->venta])
        ->assertSee('Imprimir en la caja')->call('pedirImpresion')->assertHasNoErrors()->assertSee('Esperando a la caja')->assertSee('Pedido enviado');

    $pedido = Impresion::first();
    $pantalla->call('cancelarImpresion', $pedido->id)->assertSee('Cancelado')->assertSee('Volver a imprimir en la caja');
    expect($pedido->fresh()->estado)->toBe(EstadoImpresion::Cancelado);

    // Un cajero pide el ticket de su propia venta.
    Volt::actingAs($this->cajero)->test('pages.ventas.ver', ['venta' => $this->venta])->call('pedirImpresion')->assertHasNoErrors();
});

it('la caja se configura para imprimir los pedidos solos o avisar', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();
    expect($this->caja->fresh()->impresion_pedidos)->toBe('sola');

    Volt::actingAs($admin)->test('pages.cajas')->call('editar', $this->caja->id)->assertSet('impresion_pedidos', 'sola')
        ->set('impresion_pedidos', 'aviso')->call('guardar')->assertHasNoErrors();
    expect($this->caja->fresh()->impresion_pedidos)->toBe('aviso');

    Volt::actingAs($admin)->test('pages.cajas')->call('editar', $this->caja->id)->set('impresion_pedidos', 'otra')->call('guardar')->assertHasErrors('impresion_pedidos');
});

it('las direcciones que usa la pantalla de Venta son relativas, para que funcionen detrás de un túnel o proxy', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance()->configuracionJs();

    foreach (['urlBuscar', 'urlPorIds', 'urlTicket', 'urlTicketTexto', 'urlImpresiones', 'urlImpresion'] as $clave) {
        expect($c[$clave])->toStartWith('/')->not->toContain('http');
    }
});
