<?php

namespace App\Enums;

enum TipoImpuesto: string
{
    case Iva = 'iva';
    case Ieps = 'ieps';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Iva => 'IVA',
            self::Ieps => 'IEPS',
        };
    }
}
