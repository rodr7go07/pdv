<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Porcentaje que se ofrece al cobrar cuando toda la venta se paga en efectivo (0 = apagado).
        Schema::table('configuracion', function (Blueprint $table) {
            $table->decimal('descuento_efectivo_pct', 5, 2)->default(5)->after('vender_sin_existencia');
        });

        // La venta recuerda que su descuento general fue por pagar en efectivo.
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('descuento_efectivo')->default(false)->after('descuento');
        });

        // La configuración se guarda en caché con sus columnas: sin esto seguiría sin el porcentaje nuevo.
        Cache::forget('configuracion.tienda');
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn('descuento_efectivo'));
        Schema::table('configuracion', fn (Blueprint $table) => $table->dropColumn('descuento_efectivo_pct'));
        Cache::forget('configuracion.tienda');
    }
};
