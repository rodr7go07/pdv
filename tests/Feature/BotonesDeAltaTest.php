<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
});

// Un wire:click fuera del componente no hace nada: el botón de alta debe pintarse dentro de él.
it('pinta el botón de alta dentro del componente Livewire', function (string $ruta, string $accion) {
    $html = $this->actingAs($this->admin)->get($ruta)->assertOk()->getContent();

    $inicio = strpos($html, 'wire:id=');
    $boton = strpos($html, 'wire:click="'.$accion.'"');

    expect($inicio)->not->toBeFalse()
        ->and($boton)->not->toBeFalse()
        ->and($boton)->toBeGreaterThan($inicio);
})->with([
    'cajas' => ['/cajas', 'nueva'],
    'usuarios' => ['/usuarios', 'nuevo'],
    'clientes' => ['/clientes', 'nuevo'],
    'proveedores' => ['/proveedores', 'nuevo'],
]);
