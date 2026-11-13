<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Departamento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    abrirTurno($this->caja, $this->cajero);
    [$this->bebidas, $this->pan] = [Departamento::factory()->create(['nombre' => 'Bebidas']), Departamento::factory()->create(['nombre' => 'Pan'])];
    $this->agua = Articulo::factory()->create(['descripcion' => 'AGUA', 'departamento_id' => $this->bebidas->id, 'existencia' => '100']);
    $this->coca = Articulo::factory()->create(['descripcion' => 'COCA', 'departamento_id' => $this->bebidas->id, 'existencia' => '100']);
    $this->bolillo = Articulo::factory()->create(['descripcion' => 'BOLILLO', 'departamento_id' => $this->pan->id, 'existencia' => '100']);
    Articulo::factory()->create(['descripcion' => 'BAJA', 'departamento_id' => $this->bebidas->id, 'activo' => false]);
});

function venderPiezas(User $u, Articulo $a, string $cantidad): void
{
    app(RegistrarVenta::class)(Turno::abiertoDe($u)->caja, $u, [['articulo_id' => $a->id, 'cantidad' => $cantidad]], [['forma' => 'efectivo', 'monto' => '99999']]);
}

it('los más vendidos van primero y los que no se han vendido después, por nombre', function () {
    venderPiezas($this->cajero, $this->bolillo, '20');
    venderPiezas($this->cajero, $this->coca, '5');

    $ids = collect($this->actingAs($this->cajero)->getJson('/venta/rapidos')->assertOk()->json('articulos'))->pluck('id')->all();

    expect($ids)->toBe([$this->bolillo->id, $this->coca->id, $this->agua->id]);
});

it('filtra por departamento, sin inactivos, y trae lo que necesita la caja', function () {
    venderPiezas($this->cajero, $this->coca, '3');

    $r = $this->actingAs($this->cajero)->getJson("/venta/rapidos?depto={$this->bebidas->id}")->assertOk()->json('articulos');

    expect(collect($r)->pluck('descripcion')->all())->toBe(['COCA', 'AGUA'])
        ->and($r[0])->toHaveKeys(['id', 'clave', 'precios', 'unidad', 'granel', 'decimales', 'existencia', 'imagen']);
});

it('las ventas de hace más de 30 días no cuentan para los más vendidos', function () {
    venderPiezas($this->cajero, $this->agua, '50');
    Venta::query()->update(['created_at' => now()->subDays(40)]);
    venderPiezas($this->cajero, $this->coca, '1');

    expect($this->actingAs($this->cajero)->getJson('/venta/rapidos')->json('articulos.0.id'))->toBe($this->coca->id);
});

it('solo quien cobra consulta los productos y la caja recibe la dirección y los departamentos', function () {
    $this->actingAs(User::factory()->create())->getJson('/venta/rapidos')->assertForbidden();

    $cfg = Volt::actingAs($this->cajero)->test('pages.pos')->instance()->configuracionJs();
    expect($cfg['urlRapidos'])->toBe('/venta/rapidos')
        ->and(collect($cfg['departamentosRapidos'])->pluck('nombre'))->toContain('Bebidas', 'Pan');
});
