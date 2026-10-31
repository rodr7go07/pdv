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
 */
final class TicketTexto
{
    private const ANCHO = 32;

    public static function generar(Venta $venta, ?Configuracion $tienda = null): string
    {
        $tienda ??= Configuracion::actual();
        $venta->loadMissing(['partidas', 'pagos', 'caja', 'cajero:id,name', 'devoluciones']);

        $l = [];
        $linea = str_repeat('-', self::ANCHO);
        $dinero = fn ($v) => '$'.number_format((float) $v, 2);

        $l[] = mb_strtoupper($tienda->nombre_tienda);
        foreach (array_filter([$tienda->razon_social, $tienda->rfc ? 'RFC '.$tienda->rfc : null, $tienda->direccion, $tienda->telefono ? 'Tel. '.$tienda->telefono : null]) as $dato) {
            $l[] = $dato;
        }
        $l[] = $linea;
        $l[] = self::par('Folio', $venta->folio);
        $l[] = self::par('Fecha', $venta->created_at->format('d/m/Y H:i'));
        $l[] = self::par('Atendió', $venta->cajero?->name ?? '—');

        if ($venta->estaCancelada()) {
            $l[] = '*** VENTA CANCELADA ***';
        }

        $l[] = $linea;

        foreach ($venta->partidas as $p) {
            $l[] = $p->descripcion;
            $bruto = BigDecimal::of((string) $p->total)->plus((string) $p->descuento);
            $l[] = self::par('  '.Numero::cantidad($p->cantidad).' '.$p->unidad.' x '.$dinero($p->precio_unitario), $dinero((string) $bruto));

            if ((float) $p->descuento > 0) {
                $l[] = self::par('  Descuento', '-'.$dinero($p->descuento));
            }
        }

        $l[] = $linea;
        $l[] = self::par('Subtotal', $dinero($venta->subtotal));

        foreach ($venta->desgloseImpuestos() as $i) {
            $l[] = self::par($i['nombre'], $dinero($i['monto']));
        }

        if ((float) $venta->descuento > 0) {
            $l[] = self::par('Descuentos'.($venta->descuento_efectivo ? ' (efectivo)' : ''), '-'.$dinero($venta->descuento));
        }

        $l[] = self::par('TOTAL', $dinero($venta->total));
        $l[] = $linea;

        foreach ($venta->pagos as $pago) {
            $l[] = self::par(FormaPago::etiquetaDe($pago->forma), $dinero($pago->monto));
        }

        if ((float) $venta->efectivo_recibido > 0) {
            $l[] = self::par('Efectivo recibido', $dinero($venta->efectivo_recibido));
            $l[] = self::par('Cambio', $dinero($venta->cambio));
        }

        if ($venta->devoluciones->isNotEmpty()) {
            $l[] = $linea;
            $l[] = 'Devoluciones';
            foreach ($venta->devoluciones as $d) {
                $l[] = self::par($d->folio.' '.$d->created_at->format('d/m/Y'), '-'.$dinero($d->total));
            }
        }

        $l[] = $linea;
        $l[] = $tienda->leyenda_ticket ?: 'Gracias por su compra';

        return implode("\n", $l)."\n";
    }

    /** «Etiqueta ........ valor» a todo el ancho; si no cabe, el valor va en la línea de abajo. */
    private static function par(string $izquierda, string $derecha): string
    {
        $espacio = self::ANCHO - mb_strlen($izquierda) - mb_strlen($derecha);

        return $espacio >= 1 ? $izquierda.str_repeat(' ', $espacio).$derecha : $izquierda."\n".str_pad($derecha, self::ANCHO, ' ', STR_PAD_LEFT);
    }
}
