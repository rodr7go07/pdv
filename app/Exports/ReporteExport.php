<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exportación genérica de un reporte: encabezados y filas ya calculadas. */
class ReporteExport implements Export, FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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

    public function styles(Worksheet $hoja): array
    {
        $hoja->freezePane('A2');

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '141A4A']]]];
    }
}
