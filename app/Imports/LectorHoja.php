<?php

namespace App\Imports;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as LecturaException;

/**
 * Lee la primera hoja de un .xlsx/.xls/.csv y la devuelve como filas con
 * encabezados normalizados ("Precio 1" → "precio_1").
 */
class LectorHoja
{
    public const MAX_FILAS = 5000;

    /** @return list<array<string, string>> */
    public static function leer(string $ruta): array
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $hoja = $lector->load($ruta)->getSheet(0);
        } catch (LecturaException $e) {
            throw new InvalidArgumentException('No se pudo leer el archivo. Sube un Excel (.xlsx) o un CSV.');
        }

        // Sin formato: conserva los números tal cual (un código largo no se vuelve 7.5E+12).
        $filas = $hoja->toArray(null, true, false, false);

        $encabezados = array_map(fn ($h) => Str::slug((string) $h, '_'), array_shift($filas) ?? []);

        if (! in_array('clave', $encabezados, true) || ! in_array('descripcion', $encabezados, true)) {
            throw new InvalidArgumentException('El archivo no tiene los encabezados esperados. Descarga la plantilla y usa sus columnas.');
        }

        if (count($filas) > self::MAX_FILAS) {
            throw new InvalidArgumentException('El archivo tiene más de '.self::MAX_FILAS.' filas. Divídelo en partes.');
        }

        return array_map(function (array $fila) use ($encabezados) {
            $registro = [];

            foreach ($encabezados as $i => $encabezado) {
                if ($encabezado !== '') {
                    $registro[$encabezado] = self::texto($fila[$i] ?? null);
                }
            }

            return $registro;
        }, $filas);
    }

    private static function texto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
            return number_format($valor, 0, '', '');
        }

        return trim((string) $valor);
    }
}
