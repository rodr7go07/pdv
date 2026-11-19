<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * El equipo de la tienda (decisión 88). Se corre una vez a mano, también en producción:
 *   php artisan db:seed --class=EquipoTiendaSeeder --force
 *
 * Solo crea a quien no exista (por usuario): a quien ya existe no le cambia ni la contraseña ni el rol.
 * Cada usuario nuevo recibe una contraseña al azar que se muestra UNA vez en la terminal; cada quien la cambia
 * después en su perfil. En el código no se guarda ninguna contraseña.
 */
class EquipoTiendaSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: Rol}> usuario => [nombre, rol] */
    public const EQUIPO = [
        'cris' => ['Cris', Rol::Administrador],
        'david' => ['David', Rol::Vendedor],
        'lupita' => ['Lupita', Rol::Vendedor],
    ];

    /** @var array<string, string> usuario => contraseña de los que se crearon en esta corrida */
    public array $creados = [];

    public function run(): void
    {
        $this->call(RolesYPermisosSeeder::class);

        foreach (self::EQUIPO as $usuario => [$nombre, $rol]) {
            if (User::query()->where('usuario', $usuario)->exists()) {
                $this->command?->line("  {$usuario}: ya existía, no se cambió.");

                continue;
            }

            $clave = Str::password(10, symbols: false);

            User::query()->create(['usuario' => $usuario, 'name' => $nombre, 'password' => $clave, 'activo' => true])
                ->syncRoles($rol->value);

            $this->creados[$usuario] = $clave;
            $this->command?->info("  {$usuario} ({$rol->value}): contraseña {$clave}");
        }

        if ($this->creados !== []) {
            $this->command?->warn('  Anota estas contraseñas ahora: no se vuelven a mostrar. Cada quien la cambia en su perfil.');
        }
    }
}
