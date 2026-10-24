<?php

namespace App\Enums;

enum TipoMovimiento: string
{
    case Compra = 'compra';
    case CancelacionCompra = 'cancelacion_compra';
    case Venta = 'venta';
    case CancelacionVenta = 'cancelacion_venta';
    case Devolucion = 'devolucion';
    case Ajuste = 'ajuste';
    case Conteo = 'conteo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::CancelacionCompra => 'Compra cancelada',
            self::Venta => 'Venta',
            self::CancelacionVenta => 'Venta cancelada',
            self::Devolucion => 'Devolución',
            self::Ajuste => 'Ajuste',
            self::Conteo => 'Conteo físico',
        };
    }
}
