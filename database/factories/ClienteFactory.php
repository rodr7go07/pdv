<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cliente> */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'telefono' => fake()->numerify('55########'),
            'lista_precios' => 1,
            'limite_credito' => '0',
            'activo' => true,
        ];
    }

    public function conCredito(string $limite = '1000'): static
    {
        return $this->state(fn () => ['limite_credito' => $limite]);
    }
}
