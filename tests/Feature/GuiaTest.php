<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(fn () => $this->seed(RolesYPermisosSeeder::class));

it('todos los roles pueden leer la guía', function (Rol $rol) {
    $this->actingAs(User::factory()->conRol($rol)->create())->get('/guia')
        ->assertOk()
        ->assertSee('Guía de uso')
        ->assertSee('Antes de empezar')
        ->assertSee('Cobrar a un cliente')
        ->assertSee('Cerrar tu caja al final')
        ->assertSee('Palabras que verás');
})->with([Rol::Administrador, Rol::Encargado, Rol::Cajero]);

it('los visitantes no ven la guía', function () {
    $this->get('/guia')->assertRedirect('/login');
});

it('la guía explica cada módulo del sistema', function (string $texto) {
    $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())->get('/guia')->assertSee($texto);
})->with([
    'turno' => 'Abrir tu caja para empezar a vender',
    'artículos' => 'Tus productos (artículos)',
    'compras' => 'Meter mercancía a la tienda',
    'inventario' => 'Saber cuánto tienes',
    'cancelar' => 'cancelar y devolver',
    'retiros' => 'Dinero que entra o sale de la caja',
    'crédito' => 'Clientes que te deben',
    'reportes' => 'Saber cuánto vendiste y cuánto ganaste',
    'usuarios' => 'usuarios, roles, cajas y ajustes',
    'problemas' => 'Si algo no funciona',
]);

it('la guía explica las funciones de escaneo, foto, compra sugerida e instalación', function (string $texto) {
    $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())->get('/guia')->assertSee($texto, false);
})->with([
    'lector' => 'Con un lector de código de barras',
    'cámara' => 'Usa la cámara del teléfono',
    'foto' => 'Tomar foto',
    'sugerencia' => 'Sugerencia de compra',
    'escaneos' => '¿Qué se ha escaneado?',
    'instalar' => 'Instálalo como una app',
    'roles' => 'Nuevo rol',
    'autorización' => 'Pedir autorización para cancelar',
]);

it('tiene buscador y un índice con anclas a cada sección', function () {
    $html = $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())->get('/guia')->getContent();

    expect($html)->toContain('id="buscar-guia"');

    preg_match_all('/<section id="([a-z-]+)" data-seccion/', $html, $secciones);
    expect($secciones[1])->toContain('empezar', 'vender', 'cerrar', 'glosario')->and(array_unique($secciones[1]))->toHaveCount(count($secciones[1]));

    foreach ($secciones[1] as $id) {
        expect($html)->toContain('href="#'.$id.'"');
    }
});

it('los atajos de la guía coinciden con los de la pantalla de venta', function () {
    $guia = $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())->get('/guia')->getContent();
    $pos = file_get_contents(resource_path('views/livewire/pages/pos.blade.php'));

    foreach (['F2', 'F3', 'F4', 'F6', 'F7', 'F8'] as $tecla) {
        expect($guia)->toContain($tecla)->and($pos)->toContain("['{$tecla}");
    }
});

it('los enlaces de ayuda rápida solo aparecen si el rol tiene permiso', function () {
    $cajero = $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())->get('/guia')->getContent();
    $admin = $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())->get('/guia')->getContent();

    expect($cajero)->not->toContain(route('usuarios'))->and($cajero)->not->toContain(route('configuracion'))
        ->and($admin)->toContain(route('usuarios'))->and($admin)->toContain(route('configuracion'));
});

it('la guía está en español sencillo: sin tecnicismos del sistema en los títulos', function () {
    $html = $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())->get('/guia')->getContent();

    foreach (['ESC/POS', 'transacción', 'lockForUpdate', 'endpoint', 'middleware'] as $tecnicismo) {
        expect($html)->not->toContain($tecnicismo);
    }
});
