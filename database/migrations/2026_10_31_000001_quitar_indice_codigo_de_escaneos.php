<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La búsqueda por código usa LIKE '%texto%' y el cruce con artículos va por articulos.clave:
        // este índice nunca se usa y solo encarece cada escaneo que se anota.
        Schema::table('escaneos', function (Blueprint $table) {
            $table->dropIndex(['codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('escaneos', function (Blueprint $table) {
            $table->index('codigo');
        });
    }
};
