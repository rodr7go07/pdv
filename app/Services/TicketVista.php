<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Venta;

/** Datos de la vista `ventas.ticket` (el ticket que se imprime en 58 u 80 mm). */
final class TicketVista
{
    /** @return array<string, mixed> */
    public static function datos(Venta $venta, bool $imprimir = false): array
    {
        $venta->load(['partidas', 'pagos', 'caja', 'cajero:id,name', 'devoluciones']);

        return [
            'venta' => $venta,
            'tienda' => Configuracion::actual(),
            'ancho' => in_array($venta->caja->ancho_papel, [58, 80], true) ? $venta->caja->ancho_papel : 80,
            'impuestos' => $venta->desgloseImpuestos(),
            'imprimir' => $imprimir,
        ];
    }
}
