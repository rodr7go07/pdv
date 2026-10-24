<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Cálculos de precio, margen e impuestos con precisión decimal (nunca float).
 *
 * Convenciones del sistema:
 *  - El costo va SIN impuestos.
 *  - Los precios de venta llevan los impuestos INCLUIDOS (precio al público).
 *  - El margen es la utilidad sobre el costo, en porcentaje, usando el precio sin impuestos.
 *  - Las tasas se suman (IVA 16 + IEPS 8 = 24%).
 *  - Los precios se redondean a 2 decimales, mitad hacia arriba.
 */
final class CalculadoraPrecios
{
    /** @param  list<string|int|float>  $tasas */
    public static function sumarTasas(array $tasas): string
    {
        $total = BigDecimal::zero();

        foreach ($tasas as $tasa) {
            $total = $total->plus(self::decimal($tasa));
        }

        return (string) $total->toScale(4, RoundingMode::HalfUp);
    }

    public static function precioSinImpuestos(string $precio, string $tasaPct): string
    {
        return (string) self::decimal($precio)
            ->dividedBy(self::factorImpuestos($tasaPct), 4, RoundingMode::HalfUp);
    }

    /** Precio de venta con impuestos a partir del costo y el margen deseado. */
    public static function precioDesdeMargen(string $costo, string $margenPct, string $tasaPct): string
    {
        $base = self::decimal($costo)
            ->multipliedBy(BigDecimal::one()->plus(self::decimal($margenPct)->dividedBy(100, 10, RoundingMode::HalfUp)));

        return (string) $base->multipliedBy(self::factorImpuestos($tasaPct))->toScale(2, RoundingMode::HalfUp);
    }

    /** Margen sobre el costo a partir del precio con impuestos. Nulo si el costo es cero. */
    public static function margenDesdePrecio(string $costo, string $precio, string $tasaPct): ?string
    {
        $costo = self::decimal($costo);

        if ($costo->isLessThanOrEqualTo(0)) {
            return null;
        }

        $neto = self::decimal($precio)->dividedBy(self::factorImpuestos($tasaPct), 10, RoundingMode::HalfUp);

        return (string) $neto->minus($costo)->dividedBy($costo, 10, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toScale(2, RoundingMode::HalfUp);
    }

    private static function factorImpuestos(string $tasaPct): BigDecimal
    {
        return BigDecimal::one()->plus(self::decimal($tasaPct)->dividedBy(100, 10, RoundingMode::HalfUp));
    }

    private static function decimal(string|int|float $valor): BigDecimal
    {
        $valor = trim((string) $valor);

        try {
            return BigDecimal::of($valor === '' ? '0' : $valor);
        } catch (MathException) {
            return BigDecimal::zero();
        }
    }
}
