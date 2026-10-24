<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'usuario' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function conRol(Rol $rol): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($rol->value));
    }
}
