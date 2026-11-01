<?php

namespace App\Enums;

/** Estado de un pedido de impresión de un ticket en la caja. El valor es lo que se guarda en `impresiones.estado`. */
enum EstadoImpresion: string
{
    case Pendiente = 'pendiente';
    case Imprimiendo = 'imprimiendo';
    case Impreso = 'impreso';
    case Caducado = 'caducado';
    case Cancelado = 'cancelado';
    case Fallo = 'fallo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Esperando a la caja',
            self::Imprimiendo => 'Imprimiendo',
            self::Impreso => 'Impreso',
            self::Caducado => 'No se imprimió (caducó)',
            self::Cancelado => 'Cancelado',
            self::Fallo => 'Falló',
        };
    }

    /** Tono del `<x-badge>`. */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente, self::Imprimiendo => 'aviso',
            self::Impreso => 'ok',
            self::Caducado, self::Cancelado => 'gris',
            self::Fallo => 'peligro',
        };
    }

    /** Todavía puede imprimirse. */
    public function estaVigente(): bool
    {
        return in_array($this, [self::Pendiente, self::Imprimiendo], true);
    }
}
