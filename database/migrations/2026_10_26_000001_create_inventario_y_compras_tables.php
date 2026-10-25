<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consecutivos por serie. Cada folio sale de un UPDATE ... increment atómico dentro de la transacción del documento.
        Schema::create('folios', function (Blueprint $table) {
            $table->string('serie', 20)->primary();
            $table->unsignedBigInteger('ultimo')->default(0);
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('contacto', 120)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 120)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // La existencia es un acumulado que SOLO cambia al registrar un movimiento.
        Schema::table('articulos', function (Blueprint $table) {
            $table->decimal('existencia', 12, 3)->default(0)->after('precio_3');
            $table->decimal('ultimo_costo', 12, 4)->default(0)->after('costo');
            $table->decimal('costo_promedio', 12, 4)->default(0)->after('ultimo_costo');
        });

        Schema::table('configuracion', function (Blueprint $table) {
            $table->string('metodo_costeo', 10)->default('promedio')->after('leyenda_ticket'); // ultimo | promedio
            $table->boolean('vender_sin_existencia')->default(true)->after('metodo_costeo');
        });

        // Kardex: una fila por cada cambio de existencia.
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->string('tipo', 25);
            $table->decimal('cantidad', 12, 3); // con signo: entradas (+), salidas (-)
            $table->decimal('costo_unitario', 12, 4)->nullable();
            $table->decimal('existencia_resultante', 12, 3);
            $table->nullableMorphs('referencia');
            $table->string('motivo', 30)->nullable();
            $table->string('nota', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['articulo_id', 'created_at']);
            $table->index('tipo');
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('factura', 60)->nullable();
            $table->date('fecha');
            $table->decimal('total', 12, 2)->default(0); // suma de partidas, sin impuestos
            $table->text('notas')->nullable();
            $table->string('estado', 15)->default('registrada'); // registrada | cancelada
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelada_at')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->timestamps();

            $table->index(['proveedor_id', 'fecha']);
            $table->index('fecha');
        });

        Schema::create('compra_partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->string('clave', 40);
            $table->string('descripcion', 150);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 12, 4); // sin impuestos
            $table->decimal('total', 12, 2);
        });

        Schema::create('conteos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('estado', 15)->default('en_proceso'); // en_proceso | aplicado | cancelado
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aplicado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conteo_partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('conteos')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->decimal('existencia_sistema', 12, 3); // lo que decía el sistema al contar
            $table->decimal('cantidad_contada', 12, 3);
            $table->timestamps();

            $table->unique(['conteo_id', 'articulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteo_partidas');
        Schema::dropIfExists('conteos');
        Schema::dropIfExists('compra_partidas');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('movimientos_inventario');

        Schema::table('configuracion', function (Blueprint $table) {
            $table->dropColumn(['metodo_costeo', 'vender_sin_existencia']);
        });
        Schema::table('articulos', function (Blueprint $table) {
            $table->dropColumn(['existencia', 'ultimo_costo', 'costo_promedio']);
        });

        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('folios');
    }
};
