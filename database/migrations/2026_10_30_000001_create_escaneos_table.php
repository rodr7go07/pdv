<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitácora de códigos escaneados (lector, teclado o cámara). Solo se agrega; se depura sola con el tiempo.
        Schema::create('escaneos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80);
            $table->string('origen', 20); // pantalla donde se escaneó (App\Enums\OrigenEscaneo)
            $table->string('medio', 10); // lector (lector o teclado) | camara
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
            $table->boolean('encontrado')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('codigo');
            $table->index(['encontrado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escaneos');
    }
};
