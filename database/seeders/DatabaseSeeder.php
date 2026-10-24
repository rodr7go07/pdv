<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);

        Configuracion::actual();

        // Administrador inicial. Fuera de local la contraseña debe venir de ADMIN_PASSWORD.
        $clave = env('ADMIN_PASSWORD') ?? (app()->environment('local') ? 'password' : null);
        throw_if($clave === null, \RuntimeException::class, 'Define ADMIN_PASSWORD en .env antes de sembrar el administrador.');

        $admin = User::query()->firstOrCreate(
            ['usuario' => 'admin'],
            ['name' => 'Administrador', 'password' => $clave],
        );
        $admin->syncRoles(Rol::Administrador->value);

        // Usuarios de prueba solo en desarrollo local.
        if (app()->environment('local')) {
            foreach ([
                'encargado' => ['Encargado de prueba', Rol::Encargado],
                'cajero' => ['Cajero de prueba', Rol::Cajero],
            ] as $usuario => [$nombre, $rol]) {
                User::query()
                    ->firstOrCreate(['usuario' => $usuario], ['name' => $nombre, 'password' => 'password'])
                    ->syncRoles($rol->value);
            }
        }
    }
}
