<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedidos de imprimir un ticket en la caja que hizo la venta (por ejemplo, desde el teléfono).
        // La pantalla de Venta de esa caja los revisa y los imprime sola, o avisa para que el cajero toque Imprimir.
        Schema::create('impresiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 12)->default('pendiente'); // pendiente | imprimiendo | impreso | caducado | cancelado | fallo
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('impreso_at')->nullable();
            $table->timestamps();

            $table->index(['caja_id', 'estado']);
            $table->index(['venta_id', 'estado']);
        });

        // sola = el ticket pedido se imprime apenas llega | aviso = la caja avisa y el cajero toca Imprimir
        Schema::table('cajas', function (Blueprint $table) {
            $table->string('impresion_pedidos', 8)->default('sola')->after('imprimir_automatico');
        });
    }

    public function down(): void
    {
        Schema::table('cajas', fn (Blueprint $table) => $table->dropColumn('impresion_pedidos'));
        Schema::dropIfExists('impresiones');
    }
};
