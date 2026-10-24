<?php

namespace Database\Factories;

use App\Models\Caja;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Caja> */
class CajaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Caja '.fake()->unique()->numberBetween(1, 9999),
            'serie' => strtoupper(fake()->unique()->lexify('???')),
            'ancho_papel' => 80,
            'imprimir_automatico' => false,
            'activa' => true,
        ];
    }
}
