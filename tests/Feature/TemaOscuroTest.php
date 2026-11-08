<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(fn () => $this->seed(RolesYPermisosSeeder::class));

it('el login decide el tema antes de pintar, sin parpadeo', function () {
    $this->get('/login')->assertOk()->assertSee("localStorage.getItem('tema')", false);
});

it('las pantallas con menú traen el interruptor del modo oscuro y la decisión del tema en el body', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Cambiar a modo oscuro')
        // Una vez en el <head> y otra al inicio del <body>: wire:navigate vuelve a ejecutar solo los scripts del body.
        ->and(substr_count($html, "localStorage.getItem('tema')"))->toBe(2);
});

it('el ticket impreso y el PDF no cambian con el modo oscuro: siempre son papel blanco', function () {
    expect(file_get_contents(resource_path('views/ventas/ticket.blade.php')))->not->toContain('tema-inicial')
        ->and(file_get_contents(resource_path('views/ventas/ticket-pdf.blade.php')))->not->toContain('tema-inicial');
});

it('un fondo añil oscuro fijo lleva zona-fija; las tarjetas y botones fuertes usan bg-fuerte', function () {
    // En modo oscuro la escala añil se invierte; bg-anil-900 solo es seguro en zonas que la recuperan (zona-fija).
    $faltan = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $archivo) {
        if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (file($archivo->getPathname()) as $n => $linea) {
            if (preg_match('/(?<![\w-])(bg|border)-anil-900(?![\w-])/', $linea) && ! str_contains($linea, 'zona-fija')) {
                $faltan[] = str_replace(resource_path('views/'), '', $archivo->getPathname()).':'.($n + 1);
            }
        }
    }

    expect($faltan)->toBe([], 'Usa bg-fuerte o agrega zona-fija en: '.implode(', ', $faltan));
});
