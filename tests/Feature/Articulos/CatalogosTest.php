<?php

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
});

it('el seeder base es idempotente', function () {
    $this->seed(CatalogosBaseSeeder::class);

    expect(UnidadMedida::count())->toBe(8)->and(Impuesto::count())->toBe(4)->and(Departamento::count())->toBe(1);
});

it('muestra las tres secciones de catálogos', function () {
    $this->actingAs($this->admin)->get('/catalogos')->assertOk()->assertSee('Departamentos');
    $this->actingAs($this->admin)->get('/catalogos?seccion=unidades')->assertOk()->assertSee('Kilogramo');
    $this->actingAs($this->admin)->get('/catalogos?seccion=impuestos')->assertOk()->assertSee('IVA 16%');
});

it('crea, edita y desactiva un departamento', function () {
    $c = Volt::actingAs($this->admin)->test('catalogos.departamentos')
        ->call('nuevo')->set('nombre', 'Lácteos')->call('guardar')->assertHasNoErrors();

    $depto = Departamento::where('nombre', 'Lácteos')->first();
    expect($depto)->not->toBeNull();

    $c->call('editar', $depto->id)->set('nombre', 'Lácteos y huevo')->call('guardar')->assertHasNoErrors();
    expect($depto->refresh()->nombre)->toBe('Lácteos y huevo');

    $c->call('alternar', $depto->id);
    expect($depto->refresh()->activo)->toBeFalse();
});

it('no permite departamentos repetidos', function () {
    Volt::actingAs($this->admin)->test('catalogos.departamentos')
        ->call('nuevo')->set('nombre', 'General')->call('guardar')->assertHasErrors('nombre');
});

it('no deja quitar los decimales a una unidad usada por artículos a granel', function () {
    $kilo = UnidadMedida::where('abreviatura', 'kg')->first();
    Articulo::factory()->create(['unidad_medida_id' => $kilo->id, 'granel' => true]);

    Volt::actingAs($this->admin)->test('catalogos.unidades')
        ->call('editar', $kilo->id)
        ->set('permite_decimales', false)
        ->call('guardar')
        ->assertHasErrors('permite_decimales');

    expect($kilo->refresh()->permite_decimales)->toBeTrue();
});

it('crea un impuesto y ignora la tasa si es exento', function () {
    Volt::actingAs($this->admin)->test('catalogos.impuestos')
        ->call('nuevo')
        ->set('nombre', 'IEPS 25%')->set('tipo', 'ieps')->set('tasa', '25')
        ->call('guardar')->assertHasNoErrors();

    expect(Impuesto::where('nombre', 'IEPS 25%')->value('tasa'))->toBe('25.0000');

    Volt::actingAs($this->admin)->test('catalogos.impuestos')
        ->call('nuevo')
        ->set('nombre', 'Exento 2')->set('tasa', '16')->set('exento', true)
        ->call('guardar')->assertHasNoErrors();

    expect(Impuesto::where('nombre', 'Exento 2')->value('tasa'))->toBe('0.0000');
});

it('valida la tasa y el tipo de impuesto', function () {
    Volt::actingAs($this->admin)->test('catalogos.impuestos')
        ->call('nuevo')->set('nombre', 'Raro')->set('tasa', '150')->set('tipo', 'otro')
        ->call('guardar')->assertHasErrors(['tasa', 'tipo']);
});

it('los componentes de catálogos exigen el permiso', function () {
    $encargadoSinPermiso = User::factory()->create();

    Volt::actingAs($encargadoSinPermiso)->test('catalogos.departamentos')->assertForbidden();
});
