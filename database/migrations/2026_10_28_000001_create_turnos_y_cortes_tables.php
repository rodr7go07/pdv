<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 10)->default('abierto'); // abierto | cerrado
            $table->decimal('fondo_inicial', 12, 2)->default(0);
            $table->timestamp('abierto_at');

            // Al cerrar se guarda el arqueo.
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('efectivo_esperado', 12, 2)->nullable();
            $table->decimal('efectivo_contado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable(); // contado − esperado (negativo = faltante)
            $table->string('notas', 255)->nullable();

            // Garantizan en la base de datos: un solo turno abierto por caja y por usuario.
            // Valen NULL cuando el turno está cerrado.
            $table->unsignedBigInteger('caja_abierta_id')->nullable()->unique();
            $table->unsignedBigInteger('usuario_abierto_id')->nullable()->unique();

            $table->timestamps();

            $table->index(['caja_id', 'abierto_at']);
            $table->index(['user_id', 'abierto_at']);
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->string('tipo', 10); // entrada | retiro
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['turno_id', 'tipo']);
        });

        Schema::create('cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->string('tipo', 1); // X parcial | Z final
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('datos'); // foto de las cifras al momento del corte
            $table->timestamp('created_at')->useCurrent();

            $table->index(['turno_id', 'tipo']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('turno_id')->nullable()->after('caja_id')->constrained('turnos')->restrictOnDelete();
            $table->index(['turno_id', 'estado']);
        });

        Schema::table('devoluciones', function (Blueprint $table) {
            $table->foreignId('turno_id')->nullable()->after('venta_id')->constrained('turnos')->restrictOnDelete();
            $table->index('turno_id');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
        });

        Schema::dropIfExists('cortes');
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('turnos');
    }
};
