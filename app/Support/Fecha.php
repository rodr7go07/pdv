<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Fechas que vienen de la URL o de un campo de texto: si no son válidas se ignoran en lugar de romper la pantalla.
 * Los filtros por día usan rangos (>= inicio, <= fin) para poder aprovechar los índices de la base.
 */
final class Fecha
{
    public static function dia(CarbonImmutable|string|null $valor): ?CarbonImmutable
    {
        if ($valor instanceof CarbonImmutable) {
            return $valor;
        }

        // Se acepta «2026-10-24» y también con hora («2026-10-24 15:30:00»); solo cuenta el día.
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T].*)?$/', trim((string) $valor), $m)) {
            return null;
        }

        $valor = $m[1];

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    public static function inicio(CarbonImmutable|string|null $valor): ?CarbonImmutable
    {
        return self::dia($valor)?->startOfDay();
    }

    public static function fin(CarbonImmutable|string|null $valor): ?CarbonImmutable
    {
        return self::dia($valor)?->endOfDay();
    }
}
