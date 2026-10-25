<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnidadMedida> */
class UnidadMedidaFactory extends Factory
{
    /** Cada artículo de prueba crea su propia unidad: un contador evita agotar el vocabulario de palabras «únicas» de Faker. */
    private static int $contador = 0;

    public function definition(): array
    {
        return [
            'nombre' => 'Unidad '.++self::$contador.' '.fake()->word(),
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
