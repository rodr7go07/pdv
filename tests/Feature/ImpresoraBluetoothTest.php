<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;

it('la prueba de impresora Bluetooth se abre con sesión y pide entrar sin ella', function () {
    $this->seed(RolesYPermisosSeeder::class);

    $this->get('/impresora-bluetooth')->assertRedirect('/login');

    $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())
        ->get('/impresora-bluetooth')
        ->assertOk()
        ->assertSee('Conectar impresora')
        ->assertSee('000018f0-0000-1000-8000-00805f9b34fb');
});
