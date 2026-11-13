<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Caja compartida (decisión 85): el turno es de la caja y lo usan los usuarios asignados; cada venta guarda quién vendió.
        Schema::table('cajas', function (Blueprint $table) {
            $table->boolean('compartida')->default(false)->after('activa');
        });

        Schema::create('caja_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['caja_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_usuario');
        Schema::table('cajas', fn (Blueprint $table) => $table->dropColumn('compartida'));
    }
};
