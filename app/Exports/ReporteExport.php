<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exportación genérica de un reporte: encabezados y filas ya calculadas. */
class ReporteExport extends DefaultValueBinder implements Export, FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<scalar|null>>  $filas
     * @param  list<int>  $columnasTexto  columnas (desde 0) que siempre se guardan como texto, como la clave de un artículo
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
        private readonly array $columnasTexto = [],
    ) {}

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->titulo), 0, 31);
    }

    public function headings(): array
    {
        return $this->encabezados;
    }

    public function array(): array
    {
        return $this->filas;
    }

    /**
     * Excel convertiría en número (7.5E+12, 1E5, 1.50) o en fórmula (=, +, -, @) lo que no debe:
     *  - las columnas de clave, y las cifras de 11+ dígitos o con cero inicial, se guardan como texto;
     *  - un texto que empieza con = + - @ se guarda como texto (no se ejecuta como fórmula).
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '') {
            $columna = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
            $comoTexto = in_array($columna, $this->columnasTexto, true)
                || (ctype_digit($value) && (strlen($value) >= 11 || (strlen($value) > 1 && $value[0] === '0')))
                || (str_contains("=+-@\t\r", $value[0]) && ! is_numeric($value));

            if ($comoTexto) {
                $cell->setValueExplicit($value, DataType::TYPE_STRING);

                return true;
            }
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $hoja): array
    {
        $hoja->freezePane('A2');

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '141A4A']]]];
    }
}
