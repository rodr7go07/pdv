<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proveedor principal de cada artículo: la sugerencia de compra se puede armar por proveedor.
        Schema::table('articulos', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('unidad_medida_id')->constrained('proveedores')->nullOnDelete();
        });

        // Días de la semana en que el proveedor pasa (1 = lunes … 7 = domingo), para cubrir hasta su siguiente visita.
        Schema::table('proveedores', function (Blueprint $table) {
            $table->json('dias_visita')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', fn (Blueprint $table) => $table->dropColumn('dias_visita'));

        Schema::table('articulos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proveedor_id');
        });
    }
};
