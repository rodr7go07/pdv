<?php

namespace Database\Factories;

use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Articulo> */
class ArticuloFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->numerify('75########'),
            'descripcion' => mb_strtoupper(fake()->unique()->words(3, true)),
            'departamento_id' => Departamento::factory(),
            'unidad_medida_id' => UnidadMedida::factory(),
            'costo' => '10.0000',
            'precio_1' => '15.00',
            'inventariable' => true,
            'granel' => false,
            'activo' => true,
        ];
    }
}
