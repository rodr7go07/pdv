<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteVentas;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create(['name' => 'Dueño']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Ana']);
    $this->caja = Caja::factory()->create(['serie' => 'A', 'nombre' => 'Caja 1']);
    abrirTurno($this->caja, $this->cajero);

    $this->articulo = Articulo::factory()->create(['descripcion' => 'GALLETAS', 'precio_1' => '25', 'costo' => '10', 'existencia_minima' => '5']);
    app(RegistrarVenta::class)($this->caja, $this->cajero, [['articulo_id' => $this->articulo->id, 'cantidad' => '4']], [['forma' => 'efectivo', 'monto' => '100']]);
});

it('los reportes son solo para quien tiene permiso', function () {
    $this->actingAs($this->cajero)->get('/reportes')->assertForbidden();
    $this->actingAs($this->cajero)->get('/reportes/exportar/resumen')->assertForbidden();
    $this->actingAs($this->admin)->get('/reportes')->assertOk();
});

it('los visitantes van al acceso', function () {
    $this->get('/reportes')->assertRedirect('/login');
});

it('muestra el resumen del periodo', function () {
    Volt::actingAs($this->admin)->test('pages.reportes.index')
        ->call('rango', 'hoy')
        ->assertSee('Venta neta')->assertSee('$100.00')->assertSee('Utilidad bruta')->assertSee('$60.00');
});

it('cada sección del reporte se muestra con sus datos', function (string $seccion, string $texto) {
    Volt::actingAs($this->admin)->test('pages.reportes.index')
        ->call('rango', 'hoy')
        ->set('seccion', $seccion)
        ->assertSee($texto);
})->with([
    'periodo' => ['periodo', '$100.00'],
    'departamento' => ['departamento', '$100.00'],
    'articulo' => ['articulo', 'GALLETAS'],
    'cajero' => ['cajero', 'Ana'],
    'pago' => ['pago', 'Efectivo'],
    'inventario' => ['inventario', 'Valor a costo'],
]);

it('muestra un aviso cuando no hay ventas en el periodo', function () {
    Volt::actingAs($this->admin)->test('pages.reportes.index')
        ->set('desde', '2020-01-01')->set('hasta', '2020-01-31')
        ->set('seccion', 'articulo')
        ->assertSee('Sin ventas en este periodo');
});

it('corrige un rango invertido y secciones inválidas', function () {
    Volt::actingAs($this->admin)->test('pages.reportes.index')
        ->set('desde', '2026-05-10')->set('hasta', '2026-05-01')
        ->assertSet('hasta', '2026-05-10')
        ->set('seccion', 'hackeo')->assertSet('seccion', 'resumen')
        ->set('agrupar', 'siglo')->assertSet('agrupar', 'dia');
});

it('los rangos rápidos fijan las fechas', function () {
    $c = Volt::actingAs($this->admin)->test('pages.reportes.index');

    $c->call('rango', 'ayer')->assertSet('desde', now()->subDay()->toDateString())->assertSet('hasta', now()->subDay()->toDateString());
    $c->call('rango', 'semana')->assertSet('desde', now()->subDays(6)->toDateString());
    $c->call('rango', 'mes_pasado')->assertSet('desde', now()->subMonthNoOverflow()->startOfMonth()->toDateString());
});

it('filtra por cajero', function () {
    $otro = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto']);

    Volt::actingAs($this->admin)->test('pages.reportes.index')
        ->call('rango', 'hoy')
        ->assertSee('$100.00')
        ->set('cajero', (string) $otro->id)
        ->assertDontSee('$100.00')->assertSee('$0.00');
});

it('exporta cada reporte a Excel', function (string $seccion) {
    $respuesta = $this->actingAs($this->admin)->get("/reportes/exportar/{$seccion}?desde=".now()->toDateString().'&hasta='.now()->toDateString());

    $respuesta->assertOk()->assertDownload();
    expect($respuesta->headers->get('content-disposition'))->toContain("reporte-{$seccion}-")->toContain('.xlsx');
})->with(['resumen', 'periodo', 'departamento', 'articulo', 'cajero', 'pago', 'inventario']);

it('la exportación valida la sección y las fechas', function () {
    $this->actingAs($this->admin)->get('/reportes/exportar/inexistente')->assertNotFound();
    $this->actingAs($this->admin)->get('/reportes/exportar/resumen?desde=2026-05-10&hasta=2026-05-01')->assertSessionHasErrors('hasta');
});

it('el tablero muestra lo de hoy, lo más vendido y las alertas', function () {
    $this->articulo->forceFill(['existencia' => '2'])->save(); // bajo el mínimo de 5

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('Vendido hoy')->assertSee('$100.00')
        ->assertSee('Lo más vendido hoy')->assertSee('GALLETAS')
        ->assertSee('Por reabastecer')
        ->assertSee('Cajas abiertas')->assertSee('Ana');
});

it('el cajero ve su turno y no el tablero del negocio', function () {
    $this->actingAs($this->cajero)->get('/dashboard')
        ->assertOk()
        ->assertSee('Mi turno')->assertSee('Caja 1')->assertSee('$100.00')
        ->assertDontSee('Vendido hoy')->assertDontSee('Utilidad bruta');
});

it('el tablero tolera un negocio sin ventas', function () {
    $nuevo = User::factory()->conRol(Rol::Administrador)->create();
    Venta::query()->delete();

    $this->actingAs($nuevo)->get('/dashboard')->assertOk()->assertSee('Todavía no hay ventas hoy');
});

it('unas fechas inválidas no rompen los reportes ni los listados', function () {
    $this->actingAs($this->admin)->get('/reportes?desde=abc&hasta=2026-99-99')->assertOk();
    $this->actingAs($this->admin)->get('/ventas?desde=abc')->assertOk();

    // Una fecha inválida se reemplaza por hoy en lugar de dar error.
    expect((new ReporteVentas('abc', 'xyz'))->desde->isToday())->toBeTrue();
});
