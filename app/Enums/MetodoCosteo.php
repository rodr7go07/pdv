<?php

namespace App\Enums;

/**
 * Cómo se calcula el costo con el que se mide la utilidad.
 * Se guardan SIEMPRE ambos costos; este método define cuál se usa como costo vigente.
 */
enum MetodoCosteo: string
{
    case Ultimo = 'ultimo';
    case Promedio = 'promedio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ultimo => 'Último costo (lo que pagué la última vez)',
            self::Promedio => 'Costo promedio (mezcla lo que tenía con lo nuevo)',
        };
    }
}
