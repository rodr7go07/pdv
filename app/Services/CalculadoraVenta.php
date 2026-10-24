<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Cálculo de los importes de una venta (sin tocar la base de datos).
 *
 * Los precios llevan impuestos incluidos. Para cada partida:
 *   bruto      = precio × cantidad
 *   descuento  = descuento propio + parte proporcional del descuento general
 *   total      = bruto − descuento
 *   subtotal   = total ÷ (1 + tasa)         impuestos = total − subtotal
 * Todo se redondea a centavos, mitad hacia arriba, y el descuento general se
 * reparte de forma que la suma siempre cuadre al centavo.
 */
final class CalculadoraVenta
{
    /**
     * @param  list<array{precio: string, cantidad: string, tasa: string, impuestos: list<array{nombre: string, tasa: string}>, descuento_tipo?: ?string, descuento_valor?: ?string}>  $lineas
     * @param  array{tipo?: ?string, valor?: ?string}  $descuentoGlobal  tipo: 'pct' o 'monto'
     * @return array{lineas: list<array<string, mixed>>, subtotal: string, descuento: string, impuestos: string, total: string}
     */
    public static function calcular(array $lineas, array $descuentoGlobal = []): array
    {
        $calculadas = [];
        $sumaTotales = self::cero();

        foreach ($lineas as $i => $l) {
            $bruto = self::dinero(BigDecimal::of($l['precio'])->multipliedBy($l['cantidad']));
            $descuento = self::descuentoPropio($bruto, $l['descuento_tipo'] ?? null, $l['descuento_valor'] ?? null);

            $calculadas[$i] = ['bruto' => $bruto, 'descuento_propio' => $descuento, 'total_previo' => $bruto->minus($descuento)];
            $sumaTotales = $sumaTotales->plus($calculadas[$i]['total_previo']);
        }

        $descuentoGeneral = self::descuentoGeneral($sumaTotales, $descuentoGlobal['tipo'] ?? null, $descuentoGlobal['valor'] ?? null);
        $repartos = self::repartir($descuentoGeneral, array_map(fn ($c) => $c['total_previo'], $calculadas), $sumaTotales);

        $resultado = [];
        $subtotal = $impuestos = $descuentoTotal = $total = self::cero();

        foreach ($lineas as $i => $l) {
            $c = $calculadas[$i];
            $descuentoLinea = $c['descuento_propio']->plus($repartos[$i]);
            $totalLinea = $c['bruto']->minus($descuentoLinea);

            $factor = BigDecimal::one()->plus(BigDecimal::of($l['tasa'])->dividedBy(100, 10, RoundingMode::HalfUp));
            $subtotalLinea = self::dinero($totalLinea->dividedBy($factor, 10, RoundingMode::HalfUp));
            $impuestosLinea = $totalLinea->minus($subtotalLinea);

            $resultado[$i] = [
                'bruto' => (string) $c['bruto'],
                'descuento' => (string) $descuentoLinea,
                'subtotal' => (string) $subtotalLinea,
                'impuestos' => (string) $impuestosLinea,
                'total' => (string) $totalLinea,
                'impuestos_detalle' => self::detalle($impuestosLinea, $l['impuestos'], $l['tasa']),
            ];

            $subtotal = $subtotal->plus($subtotalLinea);
            $impuestos = $impuestos->plus($impuestosLinea);
            $descuentoTotal = $descuentoTotal->plus($descuentoLinea);
            $total = $total->plus($totalLinea);
        }

        return [
            'lineas' => $resultado,
            'subtotal' => (string) $subtotal,
            'descuento' => (string) $descuentoTotal,
            'impuestos' => (string) $impuestos,
            'total' => (string) $total,
        ];
    }

    private static function descuentoPropio(BigDecimal $bruto, ?string $tipo, ?string $valor): BigDecimal
    {
        if (! $tipo || $valor === null || $valor === '' || BigDecimal::of($valor)->isZero()) {
            return self::cero();
        }

        $d = $tipo === 'pct'
            ? self::dinero($bruto->multipliedBy($valor)->dividedBy(100, 6, RoundingMode::HalfUp))
            : self::dinero(BigDecimal::of($valor));

        return $d->isGreaterThan($bruto) ? $bruto : $d;
    }

    private static function descuentoGeneral(BigDecimal $suma, ?string $tipo, ?string $valor): BigDecimal
    {
        if (! $tipo || $valor === null || $valor === '' || BigDecimal::of($valor)->isZero()) {
            return self::cero();
        }

        $d = $tipo === 'pct'
            ? self::dinero($suma->multipliedBy($valor)->dividedBy(100, 6, RoundingMode::HalfUp))
            : self::dinero(BigDecimal::of($valor));

        return $d->isGreaterThan($suma) ? $suma : $d;
    }

    /**
     * Reparte el descuento general entre las partidas en proporción a su importe, en centavos exactos:
     * a cada una le toca su parte redondeada hacia abajo y los centavos que sobran se dan, uno a uno,
     * a las que tuvieron la fracción más grande. Así la suma es exacta y ninguna partida queda con
     * un descuento negativo ni mayor que su importe.
     *
     * @param  array<int, BigDecimal>  $importes
     * @return array<int, BigDecimal>
     */
    private static function repartir(BigDecimal $descuento, array $importes, BigDecimal $suma): array
    {
        $repartos = array_fill_keys(array_keys($importes), self::cero());

        if ($descuento->isZero() || $suma->isZero()) {
            return $repartos;
        }

        $fracciones = [];
        $asignado = self::cero();

        foreach ($importes as $i => $importe) {
            $exacto = $descuento->multipliedBy($importe)->dividedBy($suma, 10, RoundingMode::HalfUp);
            $abajo = $exacto->toScale(2, RoundingMode::Down);

            $repartos[$i] = $abajo;
            $fracciones[$i] = $exacto->minus($abajo);
            $asignado = $asignado->plus($abajo);
        }

        $centavos = $descuento->minus($asignado)->multipliedBy(100)->toInt();
        $orden = array_keys($fracciones);
        usort($orden, fn ($a, $b) => $fracciones[$b]->compareTo($fracciones[$a]) ?: $b <=> $a);

        foreach ($orden as $i) {
            if ($centavos <= 0) {
                break;
            }

            if ($repartos[$i]->isLessThan($importes[$i])) {
                $repartos[$i] = $repartos[$i]->plus('0.01');
                $centavos--;
            }
        }

        return $repartos;
    }

    /**
     * Desglose por impuesto (para el ticket): reparte el impuesto de la partida
     * en proporción a la tasa de cada uno.
     *
     * @param  list<array{nombre: string, tasa: string}>  $impuestos
     * @return list<array{nombre: string, tasa: string, monto: string}>
     */
    private static function detalle(BigDecimal $impuestosLinea, array $impuestos, string $tasaTotal): array
    {
        $aplicables = array_values(array_filter($impuestos, fn ($i) => ! BigDecimal::of($i['tasa'])->isZero()));
        $tasaTotal = BigDecimal::of($tasaTotal);

        if ($aplicables === [] || $tasaTotal->isZero()) {
            return [];
        }

        $detalle = [];
        $acumulado = self::cero();

        foreach ($aplicables as $n => $imp) {
            $monto = $n === array_key_last($aplicables)
                ? $impuestosLinea->minus($acumulado)
                : self::dinero($impuestosLinea->multipliedBy($imp['tasa'])->dividedBy($tasaTotal, 10, RoundingMode::HalfUp));

            $acumulado = $acumulado->plus($monto);
            $detalle[] = ['nombre' => $imp['nombre'], 'tasa' => self::limpiar($imp['tasa']), 'monto' => (string) $monto];
        }

        return $detalle;
    }

    /** "16.0000" → "16", "8.5000" → "8.5". */
    private static function limpiar(string $numero): string
    {
        return str_contains($numero, '.') ? rtrim(rtrim($numero, '0'), '.') : $numero;
    }

    private static function cero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2);
    }

    private static function dinero(BigDecimal $valor): BigDecimal
    {
        return $valor->toScale(2, RoundingMode::HalfUp);
    }
}
