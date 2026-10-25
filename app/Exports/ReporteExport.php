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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exportación genérica de un reporte: encabezados y filas ya calculadas. */
class ReporteExport extends DefaultValueBinder implements Export, FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<scalar|null>>  $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
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
        // Un texto que empieza con = + - @ se abriría como fórmula en Excel; con una comilla al inicio queda como texto.
        return array_map(fn (array $fila) => array_map(
            fn ($v) => is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) && ! is_numeric($v) ? "'".$v : $v,
            $fila,
        ), $this->filas);
    }

    /** Los códigos de barras y claves con ceros a la izquierda se guardan como texto; Excel los mostraría como 7.5E+12 o sin ceros. */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && ctype_digit($value) && (strlen($value) >= 11 || (strlen($value) > 1 && $value[0] === '0'))) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $hoja): array
    {
        $hoja->freezePane('A2');

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '141A4A']]]];
    }
}
