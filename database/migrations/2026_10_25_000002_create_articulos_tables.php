<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('descripcion', 150)->index();
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->restrictOnDelete();

            // Costo sin impuestos; precios de venta con impuestos incluidos.
            $table->decimal('costo', 12, 4)->default(0);
            $table->decimal('precio_1', 12, 2)->default(0);
            $table->decimal('precio_2', 12, 2)->nullable();
            $table->decimal('precio_3', 12, 2)->nullable();

            $table->boolean('inventariable')->default(true);
            $table->boolean('granel')->default(false);
            $table->decimal('existencia_minima', 12, 3)->nullable();
            $table->decimal('existencia_maxima', 12, 3)->nullable();

            $table->string('imagen_ruta')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        // Códigos adicionales (otro proveedor, presentación, etc.). Únicos en todo el sistema.
        Schema::create('articulo_claves_alternas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->string('clave', 40)->unique();
            $table->timestamps();
        });

        Schema::create('articulo_impuesto', function (Blueprint $table) {
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignId('impuesto_id')->constrained('impuestos')->restrictOnDelete();
            $table->primary(['articulo_id', 'impuesto_id']);
        });

        // Una fila por cada campo de precio o costo que cambia.
        Schema::create('historial_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campo', 10); // costo | precio_1 | precio_2 | precio_3
            $table->decimal('valor_anterior', 12, 4)->nullable();
            $table->decimal('valor_nuevo', 12, 4)->nullable();
            $table->string('origen', 15)->default('manual'); // manual | importacion
            $table->timestamp('created_at')->useCurrent();

            $table->index(['articulo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_precios');
        Schema::dropIfExists('articulo_impuesto');
        Schema::dropIfExists('articulo_claves_alternas');
        Schema::dropIfExists('articulos');
    }
};
