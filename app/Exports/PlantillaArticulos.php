<?php

namespace App\Exports;

use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Libro con la hoja de captura, las instrucciones y los catálogos vigentes. */
class PlantillaArticulos implements Export, WithMultipleSheets
{
    public const ENCABEZADOS = [
        'clave', 'descripcion', 'departamento', 'unidad', 'costo', 'precio_1', 'precio_2', 'precio_3',
        'impuestos', 'inventariable', 'granel', 'existencia_minima', 'existencia_maxima', 'claves_alternas', 'activo',
    ];

    public function sheets(): array
    {
        return [
            new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function title(): string
                {
                    return 'Artículos';
                }

                public function headings(): array
                {
                    return PlantillaArticulos::ENCABEZADOS;
                }

                public function array(): array
                {
                    return [
                        ['7501055300075', 'REFRESCO COLA 600 ML', 'Bebidas', 'pza', 10.50, 18.00, 17.00, '', 'IVA 16%', 'SI', 'NO', 6, 48, '75010553', 'SI'],
                        ['FRUTA-001', 'MANZANA ROJA', 'Frutas y verduras', 'kg', 22, 35, '', '', 'IVA 0%', 'SI', 'SI', 5, 40, '', 'SI'],
                    ];
                }

                public function styles(Worksheet $hoja): array
                {
                    $hoja->freezePane('A2');

                    return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '141A4A']]]];
                }
            },
            new class implements FromArray, ShouldAutoSize, WithTitle
            {
                public function title(): string
                {
                    return 'Instrucciones';
                }

                public function array(): array
                {
                    return [
                        ['CÓMO LLENAR LA HOJA «Artículos»'],
                        ['Borra las 2 filas de ejemplo y captura tus artículos desde la fila 2. No cambies los encabezados de la fila 1.'],
                        [''],
                        ['clave', 'Obligatoria. Código de barras o clave interna. No se puede repetir. Si ya existe, se actualiza ese artículo.'],
                        ['descripcion', 'Obligatoria. Nombre que verá el cajero.'],
                        ['departamento', 'Obligatoria en artículos nuevos. Si no existe, se crea.'],
                        ['unidad', 'Obligatoria en artículos nuevos. Nombre o abreviatura de la hoja «Catálogos» (pza, kg, l…).'],
                        ['costo', 'Sin impuestos. Número, por ejemplo 10.50. Vacío = 0.'],
                        ['precio_1', 'Precio al público CON impuestos incluidos.'],
                        ['precio_2 / precio_3', 'Opcionales. Precios de mayoreo, también con impuestos incluidos.'],
                        ['impuestos', 'Nombres de la hoja «Catálogos» separados por coma, por ejemplo: IVA 16%, IEPS 8%. Vacío = sin impuestos (en artículos nuevos).'],
                        ['inventariable', 'SI o NO. Vacío = SI.'],
                        ['granel', 'SI o NO. Un artículo a granel necesita una unidad que permita decimales (kg, l, m). Vacío = NO.'],
                        ['existencia_minima / maxima', 'Opcionales. Sirven para avisar cuando el inventario baja.'],
                        ['claves_alternas', 'Opcional. Otros códigos del mismo artículo separados por coma.'],
                        ['activo', 'SI o NO. Vacío = SI.'],
                        [''],
                        ['Al actualizar un artículo existente, las celdas vacías NO borran el dato actual.'],
                        ['Si una fila tiene un error no se guarda ninguna: corrige el archivo y vuélvelo a subir.'],
                    ];
                }
            },
            new class implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Catálogos';
                }

                public function headings(): array
                {
                    return ['Departamentos', 'Unidades (nombre)', 'Unidades (abreviatura)', 'Impuestos'];
                }

                public function array(): array
                {
                    $deptos = Departamento::query()->orderBy('nombre')->pluck('nombre');
                    $unidades = UnidadMedida::query()->orderBy('nombre')->get(['nombre', 'abreviatura']);
                    $impuestos = Impuesto::query()->where('activo', true)->orderBy('nombre')->pluck('nombre');

                    $filas = max($deptos->count(), $unidades->count(), $impuestos->count());

                    return collect(range(0, max($filas - 1, 0)))->map(fn ($i) => [
                        $deptos[$i] ?? '',
                        $unidades[$i]->nombre ?? '',
                        $unidades[$i]->abreviatura ?? '',
                        $impuestos[$i] ?? '',
                    ])->all();
                }
            },
        ];
    }
}
