<?php

namespace App\Support;

/** Cómo se muestran los números que vienen de la base como texto decimal. */
final class Numero
{
    /** Cantidad sin ceros sobrantes: "10.000" → "10", "12.500" → "12.5", "100" → "100", vacío → "0". */
    public static function cantidad(string|int|float|null $valor): string
    {
        $texto = (string) $valor;

        if (str_contains($texto, '.')) {
            $texto = rtrim(rtrim($texto, '0'), '.');
        }

        return in_array($texto, ['', '-', '-0'], true) ? '0' : $texto;
    }
}
