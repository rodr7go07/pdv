<?php

namespace Database\Factories;

use App\Models\Caja;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Caja> */
class CajaFactory extends Factory
{
    /** Un contador, no azar: la caja inicial del seeder («Caja 1», serie A) y las series que fijan las pruebas no deben chocar. */
    private static int $contador = 0;

    public function definition(): array
    {
        $n = ++self::$contador;

        return [
            'nombre' => 'Caja de prueba '.$n,
            'serie' => 'S'.$n,
            'ancho_papel' => 80,
            'imprimir_automatico' => false,
            'activa' => true,
        ];
    }
}
