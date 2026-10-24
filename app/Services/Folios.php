<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Consecutivos por serie sin huecos ni duplicados.
 * Debe llamarse dentro de la misma transacción que crea el documento.
 */
final class Folios
{
    public static function siguiente(string $serie): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Los folios deben generarse dentro de una transacción.');
        }

        // El UPDATE toma directo el candado de escritura (sin pasar por uno de lectura), lo que evita bloqueos cruzados entre cobros simultáneos.
        $filas = DB::table('folios')->where('serie', $serie)->increment('ultimo');

        if ($filas === 0) {
            DB::table('folios')->insertOrIgnore(['serie' => $serie, 'ultimo' => 0]);
            DB::table('folios')->where('serie', $serie)->increment('ultimo');
        }

        return (int) DB::table('folios')->where('serie', $serie)->value('ultimo');
    }

    public static function formato(string $serie, int $numero): string
    {
        return sprintf('%s-%06d', $serie, $numero);
    }
}
