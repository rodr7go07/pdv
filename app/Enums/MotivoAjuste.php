<?php

namespace App\Enums;

enum MotivoAjuste: string
{
    case Merma = 'merma';
    case Caducidad = 'caducidad';
    case Robo = 'robo';
    case Correccion = 'correccion';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Merma => 'Merma (se dañó o se perdió)',
            self::Caducidad => 'Caducidad',
            self::Robo => 'Robo o extravío',
            self::Correccion => 'Corrección de captura',
            self::Otro => 'Otro motivo',
        };
    }

    /** Merma, caducidad y robo solo pueden ser salidas. */
    public function soloSalida(): bool
    {
        return in_array($this, [self::Merma, self::Caducidad, self::Robo], true);
    }
}
