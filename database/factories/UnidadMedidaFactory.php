<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnidadMedida> */
class UnidadMedidaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'abreviatura' => fake()->unique()->lexify('???'),
            'permite_decimales' => false,
            'activo' => true,
        ];
    }

    public function decimal(): static
    {
        return $this->state(fn () => ['permite_decimales' => true]);
    }
}
