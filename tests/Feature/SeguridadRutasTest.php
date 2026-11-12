<?php

use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;

// Candado para el sitio expuesto a internet: toda ruta nueva debe pedir sesión y un permiso,
// o estar anotada aquí a propósito. Si esta prueba falla por una ruta nueva, protégela
// (middleware `can:` o `permission:`) o agrégala a la lista que corresponda explicando por qué.

/** Rutas que se pueden abrir sin sesión. */
const RUTAS_PUBLICAS = [
    '/',                                 // solo redirige a /login
    'login',
    'up',                                // salud del servidor, no muestra datos
    'livewire/livewire.js',
    'livewire/livewire.min.js.map',
    'livewire/update',                   // Livewire vuelve a aplicar la sesión y los permisos de la pantalla
    'livewire/upload-file',              // exige URL firmada por Livewire
    'livewire/preview-file/{filename}',  // exige URL firmada por Livewire
];

/** Rutas con sesión que cualquier usuario activo puede abrir (o que revisan el permiso por dentro). */
const RUTAS_SOLO_SESION = [
    'dashboard',                 // las métricas exigen reportes.ver dentro del componente
    'guia',
    'profile',                   // solo su propia cuenta
    'impresora-bluetooth',       // página de diagnóstico, sin datos
    'logout',
    'escaneos',                  // POST: bitácora con límite de 90 por minuto
    'caja/cortes/{corte}',       // revisa caja.supervisar o que el turno sea suyo
    'caja/turnos/{turno}',       // revisa caja.supervisar o que el turno sea suyo
];

function rutasDeLaApp(): array
{
    return collect(Rutas::getRoutes()->getRoutes())
        ->reject(fn (Route $r) => str_starts_with($r->uri(), 'storage/') || str_starts_with($r->uri(), '_'))
        ->all();
}

function urlDePrueba(Route $r): string
{
    return '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $r->uri()), '/');
}

it('toda ruta pide sesión, salvo las públicas anotadas', function () {
    $sinSesion = collect(rutasDeLaApp())
        ->reject(fn (Route $r) => in_array($r->uri(), RUTAS_PUBLICAS, true))
        ->reject(fn (Route $r) => in_array('auth', $r->gatherMiddleware(), true))
        ->map(fn (Route $r) => $r->uri())
        ->values()->all();

    expect($sinSesion)->toBe([]);
});

it('toda ruta con sesión pide un permiso, salvo las anotadas', function () {
    $sinPermiso = collect(rutasDeLaApp())
        ->filter(fn (Route $r) => in_array('auth', $r->gatherMiddleware(), true))
        ->reject(fn (Route $r) => in_array($r->uri(), RUTAS_SOLO_SESION, true))
        ->reject(fn (Route $r) => collect($r->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'can:') || str_starts_with($m, 'permission:'))))
        ->map(fn (Route $r) => $r->uri())
        ->values()->all();

    expect($sinPermiso)->toBe([]);
});

it('sin sesión ninguna ruta protegida responde con datos', function () {
    foreach (rutasDeLaApp() as $ruta) {
        if (in_array($ruta->uri(), RUTAS_PUBLICAS, true)) {
            continue;
        }
        $metodo = in_array('GET', $ruta->methods(), true) ? 'get' : strtolower($ruta->methods()[0]);
        $respuesta = $this->{$metodo}(urlDePrueba($ruta));

        expect($respuesta->getStatusCode())
            ->toBeIn([302, 401, 403, 404, 405, 419], "/{$ruta->uri()} respondió {$respuesta->getStatusCode()} sin sesión");
        if ($respuesta->getStatusCode() === 302) {
            expect($respuesta->headers->get('Location'))->toEndWith('/login');
        }
    }
});

it('un usuario sin permisos no entra a ninguna pantalla que pida permiso', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $nadie = User::factory()->create(['activo' => true]);

    foreach (rutasDeLaApp() as $ruta) {
        $uri = $ruta->uri();
        if (in_array($uri, RUTAS_PUBLICAS, true) || in_array($uri, RUTAS_SOLO_SESION, true)) {
            continue;
        }
        $metodo = in_array('GET', $ruta->methods(), true) ? 'get' : strtolower($ruta->methods()[0]);
        $respuesta = $this->actingAs($nadie)->{$metodo}(urlDePrueba($ruta));

        expect($respuesta->getStatusCode())->toBeIn([403, 404], "/{$uri} respondió {$respuesta->getStatusCode()} a un usuario sin permisos");
    }
});

it('un usuario desactivado pierde la sesión en cualquier pantalla', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $inactivo = User::factory()->create(['activo' => false]);

    $this->actingAs($inactivo)->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});
