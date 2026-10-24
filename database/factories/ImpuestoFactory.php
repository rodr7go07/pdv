<?php

namespace Database\Factories;

use App\Enums\TipoImpuesto;
use App\Models\Impuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Impuesto> */
class ImpuestoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'tipo' => TipoImpuesto::Iva,
            'tasa' => '16',
            'exento' => false,
            'activo' => true,
        ];
    }

    public function iva(string $tasa = '16'): static
    {
        return $this->state(fn () => ['nombre' => "IVA {$tasa}%", 'tipo' => TipoImpuesto::Iva, 'tasa' => $tasa]);
    }

    public function exento(): static
    {
        return $this->state(fn () => ['nombre' => 'Exento', 'tasa' => '0', 'exento' => true]);
    }
}
