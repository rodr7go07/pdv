<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Abre un turno de caja para que un usuario pueda vender (la venta exige turno abierto).
 */
function abrirTurno(App\Models\Caja $caja, App\Models\User $usuario, string $fondo = '0'): App\Models\Turno
{
    return App\Models\Turno::query()->create([
        'caja_id' => $caja->id,
        'user_id' => $usuario->id,
        'estado' => 'abierto',
        'fondo_inicial' => $fondo,
        'abierto_at' => now(),
        'caja_abierta_id' => $caja->id,
        'usuario_abierto_id' => $usuario->id,
    ]);
}
