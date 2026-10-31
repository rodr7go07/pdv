<?php

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Permiso para la pantalla Roles. Solo el Administrador lo recibe de fábrica.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permiso = Permission::findOrCreate(Permiso::RolesAdministrar->value, 'web');

        Role::query()->where('name', Rol::Administrador->value)->first()?->givePermissionTo($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', Permiso::RolesAdministrar->value)->delete();
    }
};
