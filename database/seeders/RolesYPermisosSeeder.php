<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotente: se puede ejecutar en cada despliegue.
 *  - Crea los permisos que falten.
 *  - El Administrador siempre queda con todos los permisos.
 *  - Encargado y Cajero se crean con sus permisos de fábrica solo si no existían: después se editan desde la
 *    pantalla Roles y el seeder no debe pisar esos cambios. Un permiso nuevo para ellos se da en la migración que lo crea.
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
            $role = Role::findOrCreate($rol->value, 'web');

            if ($rol === Rol::Administrador || $role->wasRecentlyCreated) {
                $role->syncPermissions(array_map(fn (Permiso $p) => $p->value, $rol->permisos()));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
