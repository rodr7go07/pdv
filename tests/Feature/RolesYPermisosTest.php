<?php

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\User;
use App\Support\Menu;
use Database\Seeders\RolesYPermisosSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesYPermisosSeeder::class));

it('crea los tres roles y todos los permisos', function () {
    expect(Role::pluck('name')->all())->toEqualCanonicalizing(['Administrador', 'Encargado', 'Cajero'])
        ->and(Permission::count())->toBe(count(Permiso::cases()));
});

it('es idempotente al ejecutarse varias veces', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(count(Permiso::cases()));
});

it('el administrador tiene todos los permisos', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    foreach (Permiso::cases() as $permiso) {
        expect($admin->can($permiso->value))->toBeTrue();
    }
});

it('el encargado no administra usuarios ni configuración', function () {
    $encargado = User::factory()->conRol(Rol::Encargado)->create();

    expect($encargado->can(Permiso::VentasCancelar->value))->toBeTrue()
        ->and($encargado->can(Permiso::ArticulosEditarPrecio->value))->toBeTrue()
        ->and($encargado->can(Permiso::UsuariosAdministrar->value))->toBeFalse()
        ->and($encargado->can(Permiso::ConfiguracionEditar->value))->toBeFalse();
});

it('el cajero solo puede cobrar y operar su caja', function () {
    $cajero = User::factory()->conRol(Rol::Cajero)->create();

    expect($cajero->can(Permiso::VentasCobrar->value))->toBeTrue()
        ->and($cajero->can(Permiso::CajaAbrir->value))->toBeTrue()
        ->and($cajero->can(Permiso::VentasCancelar->value))->toBeFalse()
        ->and($cajero->can(Permiso::ArticulosEditarPrecio->value))->toBeFalse()
        ->and($cajero->can(Permiso::CajaRetiro->value))->toBeFalse();
});

it('el menú muestra solo lo que el rol puede usar', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();
    $cajero = User::factory()->conRol(Rol::Cajero)->create();

    expect(Menu::para($admin)->pluck('ruta')->all())->toContain('configuracion')
        ->and(Menu::para($cajero)->pluck('ruta')->all())->not->toContain('configuracion')
        ->and(Menu::para($cajero)->pluck('ruta')->all())->toContain('dashboard', 'profile');
});

it('el tablero se adapta al rol', function () {
    $cajero = User::factory()->conRol(Rol::Cajero)->create();
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    $this->actingAs($cajero)->get('/dashboard')->assertOk()->assertDontSee('Datos de la tienda');
    $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Datos de la tienda');
});

it('el seeder no pisa los permisos que la tienda cambió en Encargado y Cajero, pero el Administrador siempre tiene todo', function () {
    Role::findByName('Cajero')->givePermissionTo(Permiso::ReportesVer->value);
    Role::findByName('Encargado')->revokePermissionTo(Permiso::ArticulosEditarPrecio->value);
    Role::findByName('Administrador')->revokePermissionTo(Permiso::ConfiguracionEditar->value);

    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::findByName('Cajero')->hasPermissionTo(Permiso::ReportesVer->value))->toBeTrue()
        ->and(Role::findByName('Encargado')->hasPermissionTo(Permiso::ArticulosEditarPrecio->value))->toBeFalse()
        ->and(Role::findByName('Administrador')->permissions)->toHaveCount(count(Permiso::cases()));
});

it('solo el administrador administra roles de fábrica', function () {
    expect(Role::findByName('Administrador')->hasPermissionTo(Permiso::RolesAdministrar->value))->toBeTrue()
        ->and(Role::findByName('Encargado')->hasPermissionTo(Permiso::RolesAdministrar->value))->toBeFalse()
        ->and(Role::findByName('Cajero')->hasPermissionTo(Permiso::RolesAdministrar->value))->toBeFalse();
});

it('cada permiso tiene etiqueta y módulo', function () {
    foreach (Permiso::cases() as $p) {
        expect($p->etiqueta())->not->toBe('')->and($p->grupo())->not->toBe('');
    }
});
