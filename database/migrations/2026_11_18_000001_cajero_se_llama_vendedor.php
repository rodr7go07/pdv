<?php

use App\Enums\Permiso;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol «Cajero» pasa a llamarse «Vendedor» (decisión 88). Se renombra el mismo rol, así que quien lo tenía lo
 * conserva con sus permisos. Además se asegura que el Vendedor no cancele ventas por su cuenta ni autorice
 * cancelaciones: solo puede pedir que un supervisor autorice la de su propia venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cajero = Role::query()->where('name', 'Cajero')->where('guard_name', 'web')->first();
        $vendedor = Role::query()->where('name', 'Vendedor')->where('guard_name', 'web')->first();

        if ($cajero && ! $vendedor) {
            $cajero->update(['name' => 'Vendedor']);
            $vendedor = $cajero;
        } elseif ($cajero && $vendedor) {
            // Ya existía un «Vendedor» hecho en la pantalla Roles: los usuarios del Cajero se pasan a él.
            foreach ($cajero->users()->get() as $usuario) {
                $usuario->removeRole($cajero);
                $usuario->assignRole($vendedor);
            }
            $cajero->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([Permiso::VentasCancelar, Permiso::VentasAutorizarCancelacion] as $permiso) {
            if ($vendedor?->hasPermissionTo($permiso->value)) {
                $vendedor->revokePermissionTo($permiso->value);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'Vendedor')->where('guard_name', 'web')->update(['name' => 'Cajero']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
