<?php

namespace App\Services;

use App\Models\Turno;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Cifras de un turno para los cortes X y Z.
 *
 * Efectivo esperado = fondo inicial + efectivo cobrado en ventas + entradas − retiros − devoluciones.
 * Las ventas canceladas no cuentan (solo se pueden cancelar dentro de su propio turno abierto,
 * así que su efectivo ya se regresó al cliente). Tarjeta y transferencia no entran al cajón.
 */
final class ResumenTurno
{
    public const FORMAS = ['efectivo', 'tarjeta', 'transferencia'];

    /**
     * @return array{
     *   fondo_inicial: string,
     *   ventas: array{cantidad: int, total: string, subtotal: string, impuestos: string, descuento: string},
     *   pagos: array<string, string>,
     *   canceladas: array{cantidad: int, total: string},
     *   devoluciones: array{cantidad: int, total: string},
     *   entradas: string,
     *   retiros: string,
     *   efectivo_esperado: string
     * }
     */
    public static function calcular(Turno $turno): array
    {
        $ventas = DB::table('ventas')->where('turno_id', $turno->id)->where('estado', 'completada')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as total, COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(impuestos),0) as impuestos, COALESCE(SUM(descuento),0) as descuento')
            ->first();

        $canceladas = DB::table('ventas')->where('turno_id', $turno->id)->where('estado', 'cancelada')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as total')->first();

        $pagos = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->where('ventas.turno_id', $turno->id)->where('ventas.estado', 'completada')
            ->groupBy('venta_pagos.forma')
            ->selectRaw('venta_pagos.forma as forma, SUM(venta_pagos.monto) as monto')
            ->pluck('monto', 'forma');

        $devoluciones = DB::table('devoluciones')->where('turno_id', $turno->id)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as total')->first();

        $movimientos = DB::table('movimientos_caja')->where('turno_id', $turno->id)
            ->groupBy('tipo')->selectRaw('tipo, SUM(monto) as monto')->pluck('monto', 'tipo');

        $dinero = fn ($v) => (string) BigDecimal::of((string) ($v ?? 0))->toScale(2, RoundingMode::HalfUp);

        $porForma = [];
        foreach (self::FORMAS as $forma) {
            $porForma[$forma] = $dinero($pagos[$forma] ?? 0);
        }

        $esperado = BigDecimal::of((string) $turno->fondo_inicial)
            ->plus($porForma['efectivo'])
            ->plus($dinero($movimientos['entrada'] ?? 0))
            ->minus($dinero($movimientos['retiro'] ?? 0))
            ->minus($dinero($devoluciones->total));

        return [
            'fondo_inicial' => $dinero($turno->fondo_inicial),
            'ventas' => [
                'cantidad' => (int) $ventas->cantidad,
                'total' => $dinero($ventas->total),
                'subtotal' => $dinero($ventas->subtotal),
                'impuestos' => $dinero($ventas->impuestos),
                'descuento' => $dinero($ventas->descuento),
            ],
            'pagos' => $porForma,
            'canceladas' => ['cantidad' => (int) $canceladas->cantidad, 'total' => $dinero($canceladas->total)],
            'devoluciones' => ['cantidad' => (int) $devoluciones->cantidad, 'total' => $dinero($devoluciones->total)],
            'entradas' => $dinero($movimientos['entrada'] ?? 0),
            'retiros' => $dinero($movimientos['retiro'] ?? 0),
            'efectivo_esperado' => (string) $esperado->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
