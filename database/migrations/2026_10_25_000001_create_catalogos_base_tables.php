<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 40)->unique();
            $table->string('abreviatura', 10)->unique();
            // Solo las unidades que permiten decimales sirven para artículos a granel.
            $table->boolean('permite_decimales')->default(false);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('tipo', 10); // iva | ieps
            $table->decimal('tasa', 7, 4)->default(0); // porcentaje, ej. 16.0000
            $table->boolean('exento')->default(false);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuestos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('departamentos');
    }
};
