<?php

namespace App\Imports;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as LecturaException;

/**
 * Lee la primera hoja de un .xlsx/.xls/.csv y la devuelve como filas con
 * encabezados normalizados ("Precio 1" → "precio_1").
 *
 * También entiende los encabezados de las plantillas de otros sistemas de punto de venta
 * (clave1 … clave6, precio1, inv_min, "(s/n) granel", "(s/n) imp IVA 16%"…): ver ALIAS.
 */
class LectorHoja
{
    public const MAX_FILAS = 5000;

    /** Encabezado ya normalizado de otras plantillas → el que usa el importador. */
    private const ALIAS = [
        'clave1' => 'clave',
        'precio1' => 'precio_1',
        'precio2' => 'precio_2',
        'precio3' => 'precio_3',
        'inv_min' => 'existencia_minima',
        'inv_max' => 'existencia_maxima',
        'sn_inventariable' => 'inventariable',
        'sn_granel' => 'granel',
    ];

    /** Claves adicionales (clave2 … clave6): se juntan en `claves_alternas`. */
    private const CLAVES_EXTRA = ['clave2', 'clave3', 'clave4', 'clave5', 'clave6'];

    /** Casillas «(s/n) imp …» → nombre del impuesto que activan. */
    private const IMPUESTOS_SN = ['sn_imp_iva_16_16' => 'IVA 16%', 'sn_imp_ieps_8' => 'IEPS 8%'];

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
        $encabezados = array_map(fn (string $h) => in_array($h, ['clave1', 'clave'], true) && in_array('clave', $encabezados, true) ? $h : (self::ALIAS[$h] ?? $h), $encabezados);

        if (! in_array('clave', $encabezados, true) || ! in_array('descripcion', $encabezados, true)) {
            throw new InvalidArgumentException('El archivo no tiene los encabezados esperados. Descarga la plantilla y usa sus columnas.');
        }

        if (count($filas) > self::MAX_FILAS) {
            throw new InvalidArgumentException('El archivo tiene más de '.self::MAX_FILAS.' filas. Divídelo en partes.');
        }

        return array_map(function (array $fila) use ($encabezados) {
            $registro = [];

            $alternas = [];
            $impuestos = [];

            foreach ($encabezados as $i => $encabezado) {
                $valor = self::texto($fila[$i] ?? null);

                if (in_array($encabezado, self::CLAVES_EXTRA, true)) {
                    if ($valor !== '') {
                        $alternas[] = $valor;
                    }
                } elseif (isset(self::IMPUESTOS_SN[$encabezado])) {
                    if (in_array(Str::lower($valor), ['s', 'si', 'sí', '1', 'x', 'true'], true)) {
                        $impuestos[] = self::IMPUESTOS_SN[$encabezado];
                    }
                } elseif ($encabezado !== '') {
                    $registro[$encabezado] = $valor;
                }
            }

            // Las claves y los impuestos sueltos se suman a lo que traiga la columna normal, si existe.
            if ($alternas !== []) {
                $registro['claves_alternas'] = implode(',', array_filter([$registro['claves_alternas'] ?? '', ...$alternas]));
            }

            if ($impuestos !== []) {
                $registro['impuestos'] = implode(',', array_filter([$registro['impuestos'] ?? '', ...$impuestos]));
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
