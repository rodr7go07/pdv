<?php

namespace App\Exports;

use App\Models\Articulo;
use App\Support\Numero;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * El catálogo completo con las mismas columnas de la plantilla de importación (decisión 87): sirve para pasar los
 * artículos de un equipo a otro (por ejemplo, del local a producción) con Artículos → Importar, sin capturar nada.
 * Claves y claves alternas van como texto para que Excel no les quite ceros ni las vuelva notación científica.
 */
class CatalogoArticulos extends DefaultValueBinder implements FromGenerator, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    /** @param  bool  $conExistencias  false deja la columna vacía (para contar en la tienda el día que se arranque) */
    public function __construct(private readonly bool $conExistencias = true) {}

    public function title(): string
    {
        return 'Artículos';
    }

    public function headings(): array
    {
        return PlantillaArticulos::ENCABEZADOS;
    }

    public function generator(): Generator
    {
        $q = Articulo::query()
            ->with(['departamento:id,nombre', 'unidad:id,abreviatura', 'proveedor:id,nombre', 'impuestos:id,nombre', 'clavesAlternas:id,articulo_id,clave'])
            ->orderBy('id');

        foreach ($q->lazy(500) as $a) {
            $cifra = fn ($v) => $v === null ? '' : Numero::cantidad((string) $v);

            yield [
                (string) $a->clave,
                $a->descripcion,
                $a->departamento?->nombre ?? '',
                $a->unidad?->abreviatura ?? '',
                $cifra($a->costo),
                $cifra($a->precio_1),
                $cifra($a->precio_2),
                $cifra($a->precio_3),
                $a->impuestos->pluck('nombre')->join(', '),
                $a->inventariable ? 'SI' : 'NO',
                $a->granel ? 'SI' : 'NO',
                $this->conExistencias && $a->inventariable ? $cifra(max(0, (float) $a->existencia)) : '',
                $cifra($a->existencia_minima),
                $cifra($a->existencia_maxima),
                $a->proveedor?->nombre ?? '',
                $a->clavesAlternas->pluck('clave')->join(', '),
                $a->activo ? 'SI' : 'NO',
            ];
        }
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        // Columnas A (clave) y P (claves alternas): siempre texto.
        if (in_array($cell->getColumn(), ['A', 'P'], true) && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $hoja): array
    {
        $hoja->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
