<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de un solo registro con los datos de la tienda.
     */
    public function up(): void
    {
        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_tienda', 80);
            $table->string('razon_social', 150)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('leyenda_ticket', 255)->nullable();
            $table->string('logo_ruta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};
