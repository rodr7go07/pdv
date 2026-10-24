<?php

namespace Database\Seeders;

use App\Enums\TipoImpuesto;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Catálogos mínimos para empezar a capturar artículos. Idempotente.
 */
class CatalogosBaseSeeder extends Seeder
{
    public function run(): void
    {
        Departamento::query()->firstOrCreate(['nombre' => 'General']);

        foreach ([
            ['Pieza', 'pza', false],
            ['Kilogramo', 'kg', true],
            ['Gramo', 'g', true],
            ['Litro', 'l', true],
            ['Mililitro', 'ml', true],
            ['Caja', 'caja', false],
            ['Paquete', 'paq', false],
            ['Metro', 'm', true],
        ] as [$nombre, $abreviatura, $decimales]) {
            UnidadMedida::query()->firstOrCreate(
                ['abreviatura' => $abreviatura],
                ['nombre' => $nombre, 'permite_decimales' => $decimales],
            );
        }

        foreach ([
            ['IVA 16%', TipoImpuesto::Iva, '16', false],
            ['IVA 0%', TipoImpuesto::Iva, '0', false],
            ['Exento', TipoImpuesto::Iva, '0', true],
            ['IEPS 8%', TipoImpuesto::Ieps, '8', false],
        ] as [$nombre, $tipo, $tasa, $exento]) {
            Impuesto::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['tipo' => $tipo, 'tasa' => $tasa, 'exento' => $exento],
            );
        }
    }
}
