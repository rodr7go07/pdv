<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\TicketPdf;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto Cajero']);
    $this->otro = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A', 'ancho_papel' => 58]);
    abrirTurno($this->caja, $this->cajero);
    Configuracion::actual()->update(['nombre_tienda' => 'Abarrotes La Esquina']);
    $articulo = Articulo::factory()->create(['descripcion' => 'COCA COLA 600 ML', 'precio_1' => '18']);
    Articulo::query()->whereKey($articulo->id)->update(['existencia' => '50']);

    $this->venta = app(RegistrarVenta::class)(
        $this->caja, $this->cajero, [['articulo_id' => $articulo->id, 'cantidad' => '5']], [['forma' => 'efectivo', 'monto' => '100']],
        [], 1, null,
    );
});

it('genera un PDF válido del ancho del papel de la caja y de alto ajustado', function () {
    $pdf = TicketPdf::generar($this->venta);

    expect($pdf)->toStartWith('%PDF-');

    // MediaBox [0 0 ancho alto] en puntos: 58 mm = 164.4 pt; el alto es el del contenido, no una hoja carta.
    preg_match('/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m);
    expect(round((float) $m[1], 1))->toBe(164.4)
        ->and((float) $m[2])->toBeGreaterThan(100)->toBeLessThan(500);
});

it('el ancho sale de la caja: 80 mm = 226.8 pt', function () {
    $this->caja->update(['ancho_papel' => 80]);

    preg_match('/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s/', TicketPdf::generar($this->venta->fresh()), $m);
    expect(round((float) $m[1], 1))->toBe(226.8);
});

it('lo puede bajar el cajón de la venta y quien ve todas, no otro cajero', function () {
    $this->actingAs($this->cajero)->get(route('ventas.ticket-pdf', $this->venta))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->encargado)->get(route('ventas.ticket-pdf', $this->venta))->assertOk();
    $this->actingAs($this->otro)->get(route('ventas.ticket-pdf', $this->venta))->assertForbidden();
    auth()->logout();
    $this->get(route('ventas.ticket-pdf', $this->venta))->assertRedirect();
});

it('con descargar=1 se baja como archivo con el folio en el nombre', function () {
    $r = $this->actingAs($this->cajero)->get(route('ventas.ticket-pdf', ['venta' => $this->venta, 'descargar' => 1]));

    expect($r->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('ticket-')->toContain('.pdf');
});

it('un ticket cancelado y con acentos también sale en PDF', function () {
    Configuracion::actual()->update(['nombre_tienda' => 'Ñandú — Súper']);
    $this->venta->update(['estado' => 'cancelada']);

    expect(TicketPdf::generar($this->venta->fresh()))->toStartWith('%PDF-');
});
