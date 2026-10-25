<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada caja (terminal) tiene su serie de folios.
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('serie', 10)->unique();
            $table->unsignedSmallInteger('ancho_papel')->default(80); // 58 | 80 (mm)
            $table->boolean('imprimir_automatico')->default(false);
            $table->boolean('activa')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->string('folio', 30)->unique();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 15)->default('completada'); // completada | cancelada
            $table->unsignedTinyInteger('lista_precios')->default(1);

            // Importes: subtotal e impuestos van desglosados; los precios ya incluyen impuestos.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuestos', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('efectivo_recibido', 12, 2)->default(0);
            $table->decimal('cambio', 12, 2)->default(0);

            $table->timestamp('cancelada_at')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['serie', 'numero']);
            $table->index('created_at');
            $table->index(['caja_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });

        // "Foto" del momento: descripción, precio, costo e impuestos tal como estaban al vender.
        Schema::create('venta_partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->string('clave', 40);
            $table->string('descripcion', 150);
            $table->string('unidad', 10);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 2); // con impuestos
            $table->decimal('descuento', 12, 2)->default(0); // monto total descontado en la partida
            $table->decimal('costo_unitario', 12, 4)->default(0);
            $table->decimal('tasa_impuestos', 7, 4)->default(0);
            $table->decimal('subtotal', 12, 2); // sin impuestos, ya con descuento
            $table->decimal('impuestos', 12, 2);
            $table->decimal('total', 12, 2); // con impuestos, ya con descuento
            $table->json('impuestos_detalle')->nullable();
            $table->decimal('cantidad_devuelta', 12, 3)->default(0);
            $table->decimal('monto_devuelto', 12, 2)->default(0);

            $table->index('articulo_id');
        });

        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('forma', 15); // efectivo | tarjeta | transferencia | credito
            $table->decimal('monto', 12, 2); // lo que se aplicó a la venta (sin el cambio)
            $table->string('referencia', 60)->nullable();

            $table->index(['venta_id', 'forma']);
        });

        Schema::create('ventas_en_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 60)->nullable();
            $table->json('carrito');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['caja_id', 'created_at']);
        });

        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 255);
            $table->decimal('total', 12, 2);
            $table->string('forma', 15)->default('efectivo'); // cómo se regresó el dinero
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('devolucion_partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('venta_partida_id')->constrained('venta_partidas')->restrictOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('monto', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_partidas');
        Schema::dropIfExists('devoluciones');
        Schema::dropIfExists('ventas_en_espera');
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_partidas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('cajas');
    }
};
