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
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
});

it('la lista de artículos exige permiso de consulta', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->get('/articulos')->assertForbidden();
    $this->actingAs($this->cajero)->get('/articulos')->assertOk();
});

it('el cajero ve artículos y precios pero no costos ni botones de edición', function () {
    Articulo::factory()->create(['descripcion' => 'GALLETAS MARIAS', 'costo' => '7.7777', 'precio_1' => '12.50']);

    $this->actingAs($this->cajero)->get('/articulos')
        ->assertSee('GALLETAS MARIAS')
        ->assertSee('12.50')
        ->assertDontSee('7.78')
        ->assertDontSee('Importar')
        ->assertDontSee('Nuevo');

    $this->actingAs($this->admin)->get('/articulos')->assertSee('Nuevo')->assertSee('Importar')->assertSee('7.78');
});

it('el cajero no entra a crear, editar ni importar', function () {
    $articulo = Articulo::factory()->create();

    $this->actingAs($this->cajero)->get('/articulos/crear')->assertForbidden();
    $this->actingAs($this->cajero)->get("/articulos/{$articulo->id}/editar")->assertForbidden();
    $this->actingAs($this->cajero)->get('/articulos/importar')->assertForbidden();
    $this->actingAs($this->cajero)->get('/catalogos')->assertForbidden();
});

it('busca en la lista por nombre, clave y departamento', function () {
    $lacteos = Departamento::factory()->create(['nombre' => 'Lácteos']);
    Articulo::factory()->create(['descripcion' => 'LECHE ENTERA', 'clave' => 'L1', 'departamento_id' => $lacteos->id]);
    Articulo::factory()->create(['descripcion' => 'PAN BLANCO', 'clave' => 'P1']);

    Volt::actingAs($this->admin)->test('pages.articulos.index')
        ->set('buscar', 'leche')->assertSee('LECHE ENTERA')->assertDontSee('PAN BLANCO')
        ->set('buscar', '')->set('departamento', (string) $lacteos->id)->assertSee('LECHE ENTERA')->assertDontSee('PAN BLANCO');
});

it('filtra por estado y por defecto solo muestra activos', function () {
    Articulo::factory()->create(['descripcion' => 'ARTICULO VIGENTE']);
    Articulo::factory()->create(['descripcion' => 'ARTICULO DE BAJA', 'activo' => false]);

    Volt::actingAs($this->admin)->test('pages.articulos.index')
        ->assertSee('ARTICULO VIGENTE')->assertDontSee('ARTICULO DE BAJA')
        ->set('estado', 'inactivos')->assertSee('ARTICULO DE BAJA')->assertDontSee('ARTICULO VIGENTE')
        ->set('estado', 'todos')->assertSee('ARTICULO VIGENTE')->assertSee('ARTICULO DE BAJA');
});

it('el escáner abre el artículo cuando la clave coincide exacto', function () {
    $articulo = Articulo::factory()->create(['clave' => '7501055300075']);

    Volt::actingAs($this->admin)->test('pages.articulos.index')
        ->set('buscar', '7501055300075')
        ->call('buscarClave')
        ->assertRedirect(route('articulos.editar', $articulo));

    Volt::actingAs($this->admin)->test('pages.articulos.index')
        ->set('buscar', '000')
        ->call('buscarClave')
        ->assertNoRedirect()
        ->assertSee('No hay ningún artículo con la clave');
});

it('crea un artículo desde el formulario', function () {
    $pieza = UnidadMedida::where('abreviatura', 'pza')->first();
    $depto = Departamento::first();
    $iva = Impuesto::where('nombre', 'IVA 16%')->first();

    Volt::actingAs($this->admin)->test('pages.articulos.formulario')
        ->assertSet('form.iva_id', (string) $iva->id)
        ->set('form.clave', '7501')
        ->set('form.descripcion', 'REFRESCO COLA')
        ->set('form.departamento_id', (string) $depto->id)
        ->set('form.unidad_medida_id', (string) $pieza->id)
        ->set('form.costo', '10')
        ->set('form.precio_1', '15')
        ->set('form.claves_alternas', ['ALT1'])
        ->call('guardar')
        ->assertHasNoErrors();

    $articulo = Articulo::where('clave', '7501')->first();

    expect($articulo)->not->toBeNull()
        ->and($articulo->clavesAlternas->pluck('clave')->all())->toBe(['ALT1'])
        ->and($articulo->impuestos->pluck('nombre')->all())->toBe(['IVA 16%']);
});

it('muestra el error cuando la clave ya existe', function () {
    Articulo::factory()->create(['clave' => 'REPETIDA']);

    Volt::actingAs($this->admin)->test('pages.articulos.formulario')
        ->set('form.clave', 'REPETIDA')
        ->set('form.descripcion', 'X')
        ->call('guardar')
        ->assertHasErrors('form.clave');
});

it('mantiene enlazados costo, margen y precio', function () {
    $iva = Impuesto::where('nombre', 'IVA 16%')->first();

    Volt::actingAs($this->admin)->test('pages.articulos.formulario')
        ->set('form.iva_id', (string) $iva->id)
        ->set('form.costo', '100')
        ->set('form.margen', '30')
        ->assertSet('form.precio_1', '150.80')
        // Si el usuario cambia el precio, el margen se recalcula
        ->set('form.precio_1', '174')
        ->assertSet('form.margen', '50.00')
        // Si cambia el IVA, el precio se conserva y cambia el margen
        ->set('form.iva_id', '')
        ->assertSet('form.precio_1', '174')
        ->assertSet('form.margen', '74.00');
});

it('sin permiso de precios no se cambian costo ni precios', function () {
    $articulo = Articulo::factory()->create(['costo' => '10', 'precio_1' => '15']);
    $editor = User::factory()->create();
    $editor->givePermissionTo('articulos.editar');

    Volt::actingAs($editor)->test('pages.articulos.formulario', ['articulo' => $articulo])
        ->set('form.descripcion', 'NOMBRE NUEVO')
        ->set('form.precio_1', '999')
        ->call('guardar')
        ->assertHasNoErrors();

    $articulo->refresh();
    expect($articulo->descripcion)->toBe('NOMBRE NUEVO')->and($articulo->precio_1)->toBe('15.00');
});

it('la edición muestra el historial de cambios', function () {
    $articulo = Articulo::factory()->create(['costo' => '10', 'precio_1' => '15']);

    Volt::actingAs($this->admin)->test('pages.articulos.formulario', ['articulo' => $articulo])
        ->set('form.precio_1', '20')
        ->call('guardar')
        ->assertSee('Historial de costo y precios')
        ->assertSee('Precio 1');
});

it('elimina un artículo sin borrarlo de la base', function () {
    $articulo = Articulo::factory()->create();

    Volt::actingAs($this->admin)->test('pages.articulos.formulario', ['articulo' => $articulo])
        ->call('eliminar')
        ->assertRedirect(route('articulos.index'));

    expect(Articulo::count())->toBe(0)->and(Articulo::withTrashed()->count())->toBe(1);
});

it('no abre el formulario de un artículo eliminado', function () {
    $articulo = Articulo::factory()->create();
    $articulo->delete();

    $this->actingAs($this->admin)->get("/articulos/{$articulo->id}/editar")->assertNotFound();
});

it('un artículo con departamento desactivado sigue editable', function () {
    $depto = Departamento::factory()->create(['activo' => false]);
    $articulo = Articulo::factory()->create(['departamento_id' => $depto->id]);

    Volt::actingAs($this->admin)->test('pages.articulos.formulario', ['articulo' => $articulo])
        ->assertSet('form.departamento_id', (string) $depto->id)
        ->set('form.descripcion', 'CAMBIO')
        ->call('guardar')
        ->assertHasNoErrors();
});

it('al crear un artículo desde la pantalla se puede capturar cuánto hay', function () {
    $depto = Departamento::factory()->create();
    $pieza = UnidadMedida::where('abreviatura', 'pza')->first();

    $pantalla = Volt::actingAs($this->admin)->test('pages.articulos.formulario');
    $pantalla->assertSee('Existencia inicial')
        ->set('form.clave', 'COCA600')
        ->set('form.descripcion', 'COCA COLA 600 ML')
        ->set('form.departamento_id', (string) $depto->id)
        ->set('form.unidad_medida_id', (string) $pieza->id)
        ->set('form.costo', '10')
        ->set('form.precio_1', '18')
        ->set('form.existencia_inicial', '36')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Articulo::where('clave', 'COCA600')->first()->existencia)->toBe('36.000');
});

it('la existencia inicial solo aparece al crear y a quien puede ajustar inventario', function () {
    $articulo = Articulo::factory()->create();

    Volt::actingAs($this->admin)->test('pages.articulos.formulario', ['articulo' => $articulo])->assertDontSee('Existencia inicial');
    Volt::actingAs(User::factory()->create()->givePermissionTo('articulos.crear', 'articulos.ver'))->test('pages.articulos.formulario')->assertDontSee('Existencia inicial');
});
