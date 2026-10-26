<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnidadMedida> */
class UnidadMedidaFactory extends Factory
{
    /**
     * Cada artículo de prueba crea su propia unidad. El contador (y no letras al azar) evita agotar el vocabulario
     * de Faker y que el azar choque con las abreviaturas del catálogo base («pza», «paq»), que son únicas.
     */
    private static int $contador = 0;

    public function definition(): array
    {
        return [
            'nombre' => 'Unidad '.++self::$contador.' '.fake()->word(),
            'abreviatura' => 'u'.self::$contador,
            'permite_decimales' => false,
            'activo' => true,
        ];
    }

    public function decimal(): static
    {
        return $this->state(fn () => ['permite_decimales' => true]);
    }
}
