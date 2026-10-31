<?php

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Escaneo;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->usuario = User::factory()->conRol(Rol::Administrador)->create();
});

it('las pantallas con menú traen la franja de falta de internet', function () {
    $this->actingAs($this->usuario)->get('/dashboard')->assertOk()->assertSee('Sin internet.', false)->assertSee('La conexión volvió.', false);
});

it('la pantalla de venta trae la franja de falta de internet', function () {
    // Sin turno abierto la caja muestra su aviso, pero el diseño de la pantalla es el mismo.
    $this->actingAs($this->usuario)->get('/venta')->assertOk()->assertSee('Sin internet.', false);
});

it('la ventana de la cámara de la venta confirma el último artículo agregado', function () {
    abrirTurno(Caja::factory()->create(), $this->usuario);

    $this->actingAs($this->usuario)->get('/venta')->assertOk()->assertSee('Agregado:', false)->assertSee('ultimoLeido', false);
});

it('el campo de captura de la venta muestra coincidencias mientras se escribe y conserva el Enter', function () {
    abrirTurno(Caja::factory()->create(), $this->usuario);

    $this->actingAs($this->usuario)->get('/venta')->assertOk()
        ->assertSee('sugerir()', false)->assertSee('entrarOElegir()', false)->assertSee('id="sugerencias-venta"', false);
});

it('buscar por nombre para las coincidencias no anota un escaneo en la bitácora', function () {
    $a = Articulo::factory()->create(['descripcion' => 'COCA COLA 600 ML']);

    $this->actingAs($this->usuario)->getJson('/venta/buscar?q=coca', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertJsonPath('articulos.0.id', $a->id);

    expect(Escaneo::count())->toBe(0);
});
