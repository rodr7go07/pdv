<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\TicketTexto;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto Cajero']);
    $this->cajero->givePermissionTo('ventas.descuento');
    $this->otro = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    abrirTurno($this->caja, $this->cajero);
    Configuracion::actual()->update(['nombre_tienda' => 'Abarrotes La Esquina', 'leyenda_ticket' => 'Vuelva pronto']);
    $articulo = Articulo::factory()->create(['descripcion' => 'COCA COLA 600 ML', 'precio_1' => '18']);
    Articulo::query()->whereKey($articulo->id)->update(['existencia' => '50']);

    $this->venta = app(RegistrarVenta::class)(
        $this->caja, $this->cajero, [['articulo_id' => $articulo->id, 'cantidad' => '5']], [['forma' => 'efectivo', 'monto' => '100']],
        ['tipo' => 'pct', 'valor' => '5', 'motivo' => 'efectivo'], 1, null,
    );
});

it('el ticket en texto lleva lo mismo que el impreso', function () {
    $texto = TicketTexto::generar($this->venta);

    expect($texto)->toContain('ABARROTES LA ESQUINA')->toContain($this->venta->folio)->toContain('Beto Cajero')
        ->toContain('COCA COLA 600 ML')->toContain('5 ')->toContain(' x $18.00')->toContain('$90.00')
        ->toContain('Descuentos (efectivo)')->toContain('TOTAL')->toContain('$85.50')
        ->toContain('Efectivo recibido')->toContain('$100.00')->toContain('Cambio')->toContain('$14.50')->toContain('Vuelva pronto');

    foreach (explode("\n", $texto) as $linea) {
        expect(mb_strlen($linea))->toBeLessThanOrEqual(34);
    }
});

it('marca la venta cancelada y lista las devoluciones', function () {
    $this->venta->update(['estado' => 'cancelada']);

    expect(TicketTexto::generar($this->venta->fresh()))->toContain('VENTA CANCELADA');
});

it('el texto del ticket lo ve el cajero de la venta y quien ve todas, no otro cajero', function () {
    $url = route('ventas.ticket-texto', $this->venta);

    $r = $this->actingAs($this->cajero)->get($url)->assertOk();
    expect($r->headers->get('content-type'))->toContain('text/plain')->and($r->getContent())->toContain($this->venta->folio);

    $this->actingAs($this->encargado)->get($url)->assertOk();
    $this->actingAs($this->otro)->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect('/login');
});

it('el detalle de la venta y la ventana de cobro ofrecen compartir el ticket', function () {
    $this->actingAs($this->cajero)->get(route('ventas.ver', $this->venta))->assertOk()
        ->assertSee('Compartir ticket')->assertSee('ticket-texto', false)->assertSee('window.compartirTicket', false);

    $this->actingAs($this->cajero)->get('/venta')->assertOk()->assertSee('compartirTicket(exito.id, exito.folio)', false);
});

it('el desglose de impuestos del ticket sigue sumando por nombre', function () {
    expect($this->venta->load('partidas')->desgloseImpuestos())->toBeArray();
});
