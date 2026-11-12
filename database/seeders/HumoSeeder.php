<?php

namespace Database\Seeders;

use App\Actions\Caja\AbrirTurno;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos para la prueba de humo de la caja (`tests/humo/venta.cjs`) en el flujo automático de GitHub.
 * Deja usuarios de desarrollo (contraseña `password`), el catálogo de demostración y un turno abierto
 * del encargado. Solo corre en entorno local: `APP_ENV=local php artisan db:seed --class=HumoSeeder`.
 */
class HumoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('HumoSeeder solo se ejecuta en entorno local.');

            return;
        }

        // Llena la base con el catálogo de demostración: solo en una base SQLite de prueba, nunca en la MySQL con datos.
        if (config('database.connections.'.config('database.default').'.driver') !== 'sqlite') {
            $this->command?->error('HumoSeeder solo se ejecuta en una base SQLite de prueba (la actual es '.config('database.default').').');

            return;
        }

        $this->call([DatabaseSeeder::class, DemoSeeder::class]);

        $encargado = User::query()->where('usuario', 'encargado')->firstOrFail();

        if (! Turno::query()->abiertos()->where('user_id', $encargado->id)->exists()) {
            app(AbrirTurno::class)(Caja::query()->where('serie', 'A')->firstOrFail(), $encargado, '500');
        }
    }
}
