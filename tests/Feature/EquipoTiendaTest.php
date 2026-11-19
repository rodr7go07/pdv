<?php

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\EquipoTiendaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function migracionVendedor(): object
{
    return require database_path('migrations/2026_11_18_000001_cajero_se_llama_vendedor.php');
}

it('renombra el rol Cajero a Vendedor sin quitárselo a nadie y le quita cancelar ventas', function () {
    $this->seed(RolesYPermisosSeeder::class);
    // Como estaba antes: el rol se llamaba Cajero y alguien le había dado cancelar desde la pantalla Roles.
    $rol = Role::findByName('Vendedor');
    $rol->update(['name' => 'Cajero']);
    $rol->givePermissionTo([Permiso::VentasCancelar->value, Permiso::VentasAutorizarCancelacion->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $ana = User::factory()->create();
    $ana->assignRole('Cajero');

    migracionVendedor()->up();

    $ana = $ana->fresh();
    expect(Role::query()->where('name', 'Cajero')->exists())->toBeFalse()
        ->and($ana->hasRole('Vendedor'))->toBeTrue()
        ->and($ana->can(Permiso::VentasCobrar->value))->toBeTrue()
        ->and($ana->can(Permiso::VentasCancelar->value))->toBeFalse()
        ->and($ana->can(Permiso::VentasAutorizarCancelacion->value))->toBeFalse();
});

it('si ya había un rol Vendedor, pasa a él a los del Cajero', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Role::findOrCreate('Cajero', 'web');
    $beto = User::factory()->create();
    $beto->assignRole('Cajero');

    migracionVendedor()->up();

    expect(Role::query()->where('name', 'Cajero')->exists())->toBeFalse()
        ->and($beto->fresh()->hasRole('Vendedor'))->toBeTrue();
});

it('el vendedor de fábrica cobra pero no cancela ni autoriza cancelaciones', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $vendedor = User::factory()->conRol(Rol::Vendedor)->create();

    expect($vendedor->can(Permiso::VentasCobrar->value))->toBeTrue()
        ->and($vendedor->can(Permiso::VentasCancelar->value))->toBeFalse()
        ->and($vendedor->can(Permiso::VentasAutorizarCancelacion->value))->toBeFalse();
});

it('crea a Cris como administradora y a David y Lupita como vendedores, con contraseña al azar', function () {
    $seeder = app(EquipoTiendaSeeder::class);
    $seeder->run();

    $cris = User::query()->where('usuario', 'cris')->firstOrFail();
    $david = User::query()->where('usuario', 'david')->firstOrFail();
    $lupita = User::query()->where('usuario', 'lupita')->firstOrFail();

    expect($cris->hasRole(Rol::Administrador->value))->toBeTrue()
        ->and($david->hasRole(Rol::Vendedor->value))->toBeTrue()
        ->and($lupita->hasRole(Rol::Vendedor->value))->toBeTrue()
        ->and($david->can(Permiso::VentasCancelar->value))->toBeFalse()
        ->and(array_keys($seeder->creados))->toBe(['cris', 'david', 'lupita'])
        ->and(Hash::check($seeder->creados['david'], $david->password))->toBeTrue()
        ->and($seeder->creados['david'])->not->toBe($seeder->creados['lupita']);
});

it('no toca a quien ya existe al volver a correrlo', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $cris = User::factory()->conRol(Rol::Encargado)->create(['usuario' => 'cris', 'password' => 'mi-clave-123']);

    $seeder = app(EquipoTiendaSeeder::class);
    $seeder->run();
    (new EquipoTiendaSeeder)->run();

    $cris = $cris->fresh();
    expect(array_keys($seeder->creados))->toBe(['david', 'lupita'])
        ->and($cris->hasRole(Rol::Encargado->value))->toBeTrue()
        ->and(Hash::check('mi-clave-123', $cris->password))->toBeTrue()
        ->and(User::query()->whereIn('usuario', ['david', 'lupita'])->count())->toBe(2);
});
