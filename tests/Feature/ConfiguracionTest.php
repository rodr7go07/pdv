<?php

use App\Actions\Configuracion\ActualizarConfiguracion;
use App\Enums\Rol;
use App\Models\Configuracion;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Storage::fake('public');
});

it('crea la configuración por defecto la primera vez', function () {
    expect(Configuracion::count())->toBe(0);

    expect(Configuracion::actual()->nombre_tienda)->toBe('Mi tienda')
        ->and(Configuracion::count())->toBe(1);
});

it('permite ver la configuración al administrador', function () {
    $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
        ->get('/configuracion')
        ->assertOk()
        ->assertSee('Datos de la tienda');
});

it('prohíbe la configuración al cajero y al encargado', function (Rol $rol) {
    $this->actingAs(User::factory()->conRol($rol)->create())
        ->get('/configuracion')
        ->assertForbidden();
})->with([Rol::Cajero, Rol::Encargado]);

it('guarda los datos de la tienda y el RFC en mayúsculas', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('form.nombre_tienda', 'Abarrotes La Esquina')
        ->set('form.razon_social', 'Juan Pérez López')
        ->set('form.rfc', 'pelj800101ab3')
        ->set('form.direccion', 'Calle 5 de Mayo 12, Centro')
        ->set('form.telefono', '555 123 4567')
        ->set('form.leyenda_ticket', 'Vuelva pronto')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertDispatched('configuracion-guardada');

    $c = Configuracion::actual();

    expect($c->nombre_tienda)->toBe('Abarrotes La Esquina')
        ->and($c->rfc)->toBe('PELJ800101AB3')
        ->and($c->leyenda_ticket)->toBe('Vuelva pronto');
});

it('guarda como nulos los campos opcionales vacíos', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('form.nombre_tienda', 'Mi tienda')
        ->set('form.rfc', '')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Configuracion::actual()->rfc)->toBeNull();
});

it('valida el nombre y el formato del RFC', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('form.nombre_tienda', '')
        ->set('form.rfc', 'ESTO-NO-ES-RFC')
        ->call('guardar')
        ->assertHasErrors(['form.nombre_tienda' => 'required', 'form.rfc' => 'regex']);
});

it('no deja guardar a quien no tiene el permiso', function () {
    $cajero = User::factory()->conRol(Rol::Cajero)->create();

    Volt::actingAs($cajero)->test('pages.configuracion')->assertForbidden();
});

it('sube el logotipo y borra el anterior al reemplazarlo', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('form.nombre_tienda', 'Mi tienda')
        ->set('logo', UploadedFile::fake()->image('uno.png', 200, 200))
        ->call('guardar')
        ->assertHasNoErrors();

    $primero = Configuracion::actual()->logo_ruta;
    Storage::disk('public')->assertExists($primero);

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('logo', UploadedFile::fake()->image('dos.png', 200, 200))
        ->call('guardar');

    $segundo = Configuracion::actual()->logo_ruta;

    expect($segundo)->not->toBe($primero);
    Storage::disk('public')->assertExists($segundo);
    Storage::disk('public')->assertMissing($primero);
});

it('rechaza logotipos que no son imagen o pesan demasiado', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('logo', UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'))
        ->assertHasErrors('logo')
        ->assertSet('logo', null);

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('logo', UploadedFile::fake()->image('enorme.png')->size(2048))
        ->assertHasErrors('logo')
        ->assertSet('logo', null);
});

it('quita el logotipo cuando se pide', function () {
    $ruta = UploadedFile::fake()->image('logo.png')->store('logos', 'public');
    Configuracion::actual()->update(['logo_ruta' => $ruta]);

    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.configuracion')
        ->set('quitarLogo', true)
        ->call('guardar');

    expect(Configuracion::actual()->logo_ruta)->toBeNull();
    Storage::disk('public')->assertMissing($ruta);
});

it('la acción conserva el logotipo si no se toca', function () {
    $ruta = UploadedFile::fake()->image('logo.png')->store('logos', 'public');
    $c = Configuracion::actual();
    $c->update(['logo_ruta' => $ruta]);

    app(ActualizarConfiguracion::class)($c, ['nombre_tienda' => 'Nuevo nombre']);

    expect(Configuracion::actual()->logo_ruta)->toBe($ruta);
    Storage::disk('public')->assertExists($ruta);
});

it('refleja el nombre de la tienda en la pantalla de acceso', function () {
    Configuracion::actual()->update(['nombre_tienda' => 'Abarrotes La Esquina']);

    $this->get('/login')->assertSee('Abarrotes La Esquina');
});
