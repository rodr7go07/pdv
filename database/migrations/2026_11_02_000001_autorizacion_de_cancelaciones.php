<?php

use App\Enums\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Quién autorizó la cancelación (un supervisor que escribió su usuario y contraseña, o quien canceló directo).
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('autorizada_por')->nullable()->after('cancelada_por')->constrained('users')->nullOnDelete();
        });

        // Cancelación de una venta cuyo turno ya cerró: se hace como devolución total, marcada para distinguirla.
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->boolean('es_cancelacion')->default(false)->after('forma');
        });

        // Permiso nuevo para quien puede autorizar cancelaciones. Se da a los roles que ya cancelan ventas
        // (el seeder no reescribe los permisos de roles existentes, así que se hace aquí).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permiso = Permission::findOrCreate(Permiso::VentasAutorizarCancelacion->value, 'web');

        foreach (Role::query()->get() as $rol) {
            if ($rol->hasPermissionTo(Permiso::VentasCancelar->value)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('devoluciones', fn (Blueprint $table) => $table->dropColumn('es_cancelacion'));

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('autorizada_por');
        });

        Permission::query()->where('name', Permiso::VentasAutorizarCancelacion->value)->delete();
    }
};
