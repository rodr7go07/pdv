<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entrada rápida de mercancía (decisión 84): se guarda como compra, pero el proveedor es opcional.
        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo', 10)->default('compra')->after('folio'); // compra | entrada
            $table->foreignId('proveedor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
