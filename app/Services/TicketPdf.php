<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Venta;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * El ticket de una venta como PDF del ancho del papel de su caja (58 u 80 mm) y del alto justo del contenido,
 * para compartirlo por WhatsApp, correo, etc. Usa el mismo cuerpo que el ticket impreso (`ventas.partials.ticket-cuerpo`).
 */
final class TicketPdf
{
    private const MM_A_PUNTOS = 72 / 25.4;

    /** Alto máximo de una página PDF (14 400 pt); un ticket más largo sigue en otra página. */
    private const ALTO_MAXIMO = 14400;

    public static function generar(Venta $venta): string
    {
        $datos = TicketVista::datos($venta);
        $ancho = $datos['ancho'] * self::MM_A_PUNTOS;
        $html = view('ventas.ticket-pdf', $datos + ['logo' => self::logoEnLinea($datos['tienda'])])->render();

        // Primera pasada en una hoja larguísima para medir cuánto ocupa el contenido; la segunda ya sale del alto justo.
        $alto = 0.0;
        self::pdf($html, $ancho, self::ALTO_MAXIMO, function (Frame $frame) use (&$alto) {
            $alto = max($alto, $frame->get_position('y') + $frame->get_margin_height());
        });

        return self::pdf($html, $ancho, min(self::ALTO_MAXIMO, ceil($alto) + 8));
    }

    /** El logo va incrustado en el PDF (no se permite descargar imágenes de internet al generarlo). */
    private static function logoEnLinea(Configuracion $tienda): ?string
    {
        $disco = Storage::disk('public');

        if (! $tienda->logo_ruta || ! $disco->exists($tienda->logo_ruta)) {
            return null;
        }

        $tipo = $disco->mimeType($tienda->logo_ruta);

        return in_array($tipo, ['image/png', 'image/jpeg', 'image/gif'], true)
            ? 'data:'.$tipo.';base64,'.base64_encode($disco->get($tienda->logo_ruta))
            : null;
    }

    private static function pdf(string $html, float $ancho, float $alto, ?callable $alTerminarCuadro = null): string
    {
        $carpeta = storage_path('framework/cache/dompdf');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }

        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans Mono');
        $opciones->set('tempDir', $carpeta);
        $opciones->set('fontCache', $carpeta);

        $dompdf = new Dompdf($opciones);
        if ($alTerminarCuadro) {
            $dompdf->setCallbacks([['event' => 'end_frame', 'f' => $alTerminarCuadro]]);
        }
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, $ancho, $alto]);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
