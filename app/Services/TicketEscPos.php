<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Venta;

/**
 * El ticket de una venta en comandos ESC/POS, para mandarlo directo a la térmica con RawBT (Android) sin cuadro de impresión.
 * El contenido es el mismo de `TicketTexto` (una sola fuente) al ancho de la impresora; aquí solo se le da formato:
 * encabezado centrado, nombre de la tienda y TOTAL en negritas, avance y corte de papel al final.
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
        $venta->loadMissing('caja');
        $lineas = explode("\n", rtrim(TicketTexto::generar($venta, $tienda, self::columnas((int) $venta->caja?->ancho_papel))));
        $separador = array_search(true, array_map(fn ($l) => $l !== '' && trim($l, '-') === '', $lineas), true);

        $b = self::ESC.'@'.self::ESC.'t'.chr(0); // reinicia la impresora y elige PC437

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
