<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Venta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * El ticket de una venta en comandos ESC/POS, para mandarlo directo a la térmica con RawBT (Android) sin cuadro de impresión.
 * El contenido es el mismo de `TicketTexto` (una sola fuente) al ancho de la impresora; aquí solo se le da formato:
 * logo de la tienda (si hay) como imagen en blanco y negro, encabezado centrado, nombre de la tienda y TOTAL en negritas,
 * avance y corte de papel al final.
 *
 * Los acentos y la ñ van en la página de códigos PC437, la que traen de fábrica casi todas las térmicas.
 */
final class TicketEscPos
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    /** Columnas de la letra normal (fuente A) según el ancho del papel. */
    public static function columnas(int $anchoPapel): int
    {
        return $anchoPapel === 58 ? 32 : 48;
    }

    public static function generar(Venta $venta, ?Configuracion $tienda = null): string
    {
        $tienda ??= Configuracion::actual();
        $venta->loadMissing('caja');
        $anchoPapel = (int) $venta->caja?->ancho_papel;
        $lineas = explode("\n", rtrim(TicketTexto::generar($venta, $tienda, self::columnas($anchoPapel))));
        $separador = array_search(true, array_map(fn ($l) => $l !== '' && trim($l, '-') === '', $lineas), true);

        $b = self::ESC.'@'.self::ESC.'t'.chr(0); // reinicia la impresora y elige PC437

        if ($logo = self::logo($tienda, $anchoPapel)) {
            $b .= self::ESC.'a'.chr(1).$logo.self::ESC.'d'.chr(1);
        }

        foreach ($lineas as $i => $linea) {
            $encabezado = $separador !== false && $i < $separador;
            $esTotal = str_starts_with($linea, 'TOTAL ');
            $esLeyenda = $i === array_key_last($lineas);

            $b .= self::ESC.'a'.chr($encabezado || $esLeyenda ? 1 : 0); // centrado o a la izquierda

            if ($i === 0 || $esTotal) {
                $b .= self::ESC.'E'.chr(1).self::GS.'!'.chr(0x01).self::pc437($linea)."\n".self::GS.'!'.chr(0).self::ESC.'E'.chr(0);
            } else {
                $b .= self::pc437($linea)."\n";
            }
        }

        // Avanza para que el ticket salga de la cuchilla y corta (las que no tienen cuchilla ignoran el corte).
        return $b.self::ESC.'a'.chr(0).self::ESC.'d'.chr(4).self::GS.'V'.chr(66).chr(0);
    }

    /**
     * Cualquier texto ya acomodado a las columnas del papel (como la hoja de existencias) en ESC/POS: el primer
     * renglón en negritas y doble alto, los tres primeros centrados; al final avanza y corta.
     */
    public static function documento(string $texto): string
    {
        $b = self::ESC.'@'.self::ESC.'t'.chr(0);
        foreach (explode("\n", rtrim($texto, "\n")) as $i => $linea) {
            $b .= self::ESC.'a'.chr($i < 3 ? 1 : 0);
            $b .= $i === 0
                ? self::ESC.'E'.chr(1).self::GS.'!'.chr(0x01).self::pc437(trim($linea))."\n".self::GS.'!'.chr(0).self::ESC.'E'.chr(0)
                : self::pc437($i < 3 ? trim($linea) : $linea)."\n";
        }

        return $b.self::ESC.'a'.chr(0).self::ESC.'d'.chr(4).self::GS.'V'.chr(66).chr(0);
    }

    /**
     * El logo de la tienda como imagen de puntos (GS v 0): blanco y negro, centrado, a 192 puntos de ancho en 58 mm
     * (256 en 80 mm). Lo transparente cuenta como blanco y los colores oscuros (el rosa también) como negro.
     * Se guarda en caché por archivo y ancho. Si no hay logo o no se puede leer (por ejemplo un SVG), el ticket sale sin él.
     */
    public static function logo(Configuracion $tienda, int $anchoPapel): string
    {
        $ruta = $tienda->logo_ruta;
        $disco = Storage::disk('public');

        if (! $ruta || ! function_exists('imagecreatefromstring') || ! $disco->exists($ruta)) {
            return '';
        }

        $ancho = $anchoPapel === 58 ? 192 : 256;
        $clave = 'ticket-logo:'.md5($ruta.'|'.$disco->lastModified($ruta).'|'.$disco->size($ruta)).':'.$ancho;

        return base64_decode(Cache::rememberForever($clave, fn () => base64_encode(self::rasterizar($disco->get($ruta), $ancho))));
    }

    private static function rasterizar(string $imagen, int $anchoMaximo): string
    {
        $origen = @imagecreatefromstring($imagen);

        if (! $origen) {
            return '';
        }

        // Cabe en un cuadro de anchoMaximo × anchoMaximo para que un logo alto no se coma el ticket.
        $escala = min($anchoMaximo / imagesx($origen), $anchoMaximo / imagesy($origen));
        $w = max(8, (int) round(imagesx($origen) * $escala));
        $h = max(1, (int) round(imagesy($origen) * $escala));

        $lienzo = imagecreatetruecolor($w, $h);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $w, $h, imagesx($origen), imagesy($origen));

        $bytesPorFila = (int) ceil($w / 8);
        $filas = [];

        for ($y = 0; $y < $h; $y++) {
            $fila = '';
            for ($xb = 0; $xb < $bytesPorFila; $xb++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $xb * 8 + $bit;
                    if ($x < $w) {
                        $c = imagecolorat($lienzo, $x, $y);
                        $luz = ((($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114) / 1000;
                        if ($luz < 160) {
                            $byte |= 0x80 >> $bit;
                        }
                    }
                }
                $fila .= chr($byte);
            }
            $filas[] = $fila;
        }

        // Quita las filas en blanco de arriba y de abajo (el margen del archivo no se imprime).
        $vacia = str_repeat("\0", $bytesPorFila);
        while ($filas && $filas[0] === $vacia) {
            array_shift($filas);
        }
        while ($filas && end($filas) === $vacia) {
            array_pop($filas);
        }

        if (! $filas) {
            return '';
        }

        $alto = count($filas);

        return self::GS.'v0'.chr(0).chr($bytesPorFila & 0xFF).chr($bytesPorFila >> 8).chr($alto & 0xFF).chr($alto >> 8).implode('', $filas);
    }

    /** Pasa el texto a PC437; lo que esa página no tiene (mayúsculas con acento, símbolos) se cambia por su letra simple. */
    private static function pc437(string $texto): string
    {
        static $tabla = [
            'á' => "\xA0", 'é' => "\x82", 'í' => "\xA1", 'ó' => "\xA2", 'ú' => "\xA3", 'ü' => "\x81",
            'ñ' => "\xA4", 'Ñ' => "\xA5", 'É' => "\x90", 'Ü' => "\x9A", '¿' => "\xA8", '¡' => "\xAD", '°' => "\xF8",
            'Á' => 'A', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', '—' => '-', '–' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
        ];

        $salida = '';

        foreach (mb_str_split($texto) as $c) {
            if (isset($tabla[$c])) {
                $salida .= $tabla[$c];
            } elseif (strlen($c) === 1) {
                $salida .= $c;
            } else {
                $simple = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $c);
                $salida .= $simple !== false && $simple !== '' ? $simple : '?';
            }
        }

        return $salida;
    }
}
