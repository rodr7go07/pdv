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

        DB::table('folios')->insertOrIgnore(['serie' => $serie, 'ultimo' => 0]);

        $actual = (int) DB::table('folios')->where('serie', $serie)->lockForUpdate()->value('ultimo');
        $siguiente = $actual + 1;

        DB::table('folios')->where('serie', $serie)->update(['ultimo' => $siguiente]);

        return $siguiente;
    }

    public static function formato(string $serie, int $numero): string
    {
        return sprintf('%s-%06d', $serie, $numero);
    }
}
