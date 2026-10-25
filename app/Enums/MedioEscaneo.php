<?php

namespace App\Enums;

/** Cómo llegó el código. El sistema no distingue un lector USB de alguien que escribe y da Enter: ambos son «lector». */
enum MedioEscaneo: string
{
    case Lector = 'lector';
    case Camara = 'camara';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lector => 'Lector o teclado',
            self::Camara => 'Cámara',
        };
    }
}
