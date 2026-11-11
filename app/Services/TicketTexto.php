<?php

namespace App\Services;

use App\Enums\FormaPago;
use App\Models\Configuracion;
use App\Models\Venta;
use App\Support\Numero;
use Brick\Math\BigDecimal;

/**
 * El ticket de una venta como texto plano, para compartirlo por WhatsApp u otra app desde el teléfono.
 * Lleva lo mismo que el ticket impreso (en columnas de 32 caracteres, que se ven bien en un chat).
 * `TicketEscPos` usa este mismo texto al ancho de la impresora (32 columnas en 58 mm, 48 en 80 mm).
 */
final class TicketTexto
{
    private const ANCHO = 32;

    public static function generar(Venta $venta, ?Configuracion $tienda = null, int $ancho = self::ANCHO): string
    {
        $tienda ??= Configuracion::actual();
        $venta->loadMissing(['partidas', 'pagos', 'caja', 'cajero:id,name', 'devoluciones']);

        $l = [];
        $linea = str_repeat('-', $ancho);
        $dinero = fn ($v) => '$'.number_format((float) $v, 2);

        $l[] = mb_strtoupper($tienda->nombre_tienda);
        foreach (array_filter([$tienda->razon_social, $tienda->rfc ? 'RFC '.$tienda->rfc : null, $tienda->direccion, $tienda->telefono ? 'Tel. '.$tienda->telefono : null]) as $dato) {
            $l[] = $dato;
        }
        $l[] = $linea;
        $l[] = self::par($ancho, 'Folio', $venta->folio);
        $l[] = self::par($ancho, 'Fecha', $venta->created_at->format('d/m/Y H:i'));
        $l[] = self::par($ancho, 'Atendió', $venta->cajero?->name ?? '—');

        if ($venta->estaCancelada()) {
            $l[] = '*** VENTA CANCELADA ***';
        }

        $l[] = $linea;

        foreach ($venta->partidas as $p) {
            $l[] = $p->descripcion;
            $bruto = BigDecimal::of((string) $p->total)->plus((string) $p->descuento);
            $l[] = self::par($ancho, '  '.Numero::cantidad($p->cantidad).' '.$p->unidad.' x '.$dinero($p->precio_unitario), $dinero((string) $bruto));

            if ((float) $p->descuento > 0) {
                $l[] = self::par($ancho, '  Descuento', '-'.$dinero($p->descuento));
            }
        }

        $l[] = $linea;
        $l[] = self::par($ancho, 'Subtotal', $dinero($venta->subtotal));

        foreach ($venta->desgloseImpuestos() as $i) {
            $l[] = self::par($ancho, $i['nombre'], $dinero($i['monto']));
        }

        if ((float) $venta->descuento > 0) {
            $l[] = self::par($ancho, 'Descuentos'.($venta->descuento_efectivo ? ' (efectivo)' : ''), '-'.$dinero($venta->descuento));
        }

        $l[] = self::par($ancho, 'TOTAL', $dinero($venta->total));
        $l[] = $linea;

        foreach ($venta->pagos as $pago) {
            $l[] = self::par($ancho, FormaPago::etiquetaDe($pago->forma), $dinero($pago->monto));
        }

        if ((float) $venta->efectivo_recibido > 0) {
            $l[] = self::par($ancho, 'Efectivo recibido', $dinero($venta->efectivo_recibido));
            $l[] = self::par($ancho, 'Cambio', $dinero($venta->cambio));
        }

        if ($venta->devoluciones->isNotEmpty()) {
            $l[] = $linea;
            $l[] = 'Devoluciones';
            foreach ($venta->devoluciones as $d) {
                $l[] = self::par($ancho, $d->folio.' '.$d->created_at->format('d/m/Y'), '-'.$dinero($d->total));
            }
        }

        $l[] = $linea;
        $l[] = $tienda->leyenda_ticket ?: 'Gracias por su compra';

        return implode("\n", $l)."\n";
    }

    /** «Etiqueta ........ valor» a todo el ancho; si no cabe, el valor va en la línea de abajo. */
    private static function par(int $ancho, string $izquierda, string $derecha): string
    {
        $espacio = $ancho - mb_strlen($izquierda) - mb_strlen($derecha);

        return $espacio >= 1 ? $izquierda.str_repeat(' ', $espacio).$derecha : $izquierda."\n".str_pad($derecha, $ancho, ' ', STR_PAD_LEFT);
    }
}
