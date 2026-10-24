<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotente: se puede ejecutar en cada despliegue para sincronizar permisos nuevos.
 */
class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permiso::cases() as $permiso) {
            Permission::findOrCreate($permiso->value, 'web');
        }

        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web')
                ->syncPermissions(array_map(fn (Permiso $p) => $p->value, $rol->permisos()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
