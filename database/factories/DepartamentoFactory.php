<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Departamento> */
class DepartamentoFactory extends Factory
{
    private static int $contador = 0;

    public function definition(): array
    {
        return [
            'nombre' => 'Departamento '.++self::$contador.' '.fake()->word(),
            'activo' => true,
        ];
    }
}
