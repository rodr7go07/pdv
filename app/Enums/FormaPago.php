<?php

namespace App\Enums;

/** Forma en que se paga una venta. El valor es lo que se guarda en `venta_pagos.forma`. */
enum FormaPago: string
{
    case Efectivo = 'efectivo';
    case Tarjeta = 'tarjeta';
    case Transferencia = 'transferencia';
    case Credito = 'credito';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Tarjeta => 'Tarjeta',
            self::Transferencia => 'Transferencia',
            self::Credito => 'Crédito',
        };
    }

    /** En el corte de caja el crédito aclara que ese dinero no entró. */
    public function etiquetaCorte(): string
    {
        return $this === self::Credito ? 'A crédito (sin cobrar)' : $this->etiqueta();
    }

    /** Etiqueta de un valor guardado; si ya no existe la forma, devuelve el valor tal cual. */
    public static function etiquetaDe(?string $valor): string
    {
        return self::tryFrom((string) $valor)?->etiqueta() ?? (string) $valor;
    }

    /** Todas las formas con las que se puede pagar una venta. @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Las que entran dinero en el momento (un abono no puede ser «a crédito»). @return list<string> */
    public static function cobradas(): array
    {
        return array_values(array_diff(self::valores(), [self::Credito->value]));
    }
}
