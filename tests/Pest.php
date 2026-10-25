<?php

use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Las pruebas de Feature usan la base de datos (SQLite en memoria) y se reinician en cada una.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Ayudas compartidas entre archivos de prueba.

/**
 * Abre un turno de caja para que un usuario pueda vender (la venta exige turno abierto).
 */
function abrirTurno(Caja $caja, User $usuario, string $fondo = '0'): Turno
{
    return Turno::query()->create([
        'caja_id' => $caja->id,
        'user_id' => $usuario->id,
        'estado' => 'abierto',
        'fondo_inicial' => $fondo,
        'abierto_at' => now(),
        'caja_abierta_id' => $caja->id,
        'usuario_abierto_id' => $usuario->id,
    ]);
}
