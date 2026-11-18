<?php

namespace App\Console\Commands;

use App\Exports\CatalogoArticulos;
use App\Models\Articulo;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Guarda todo el catálogo en un Excel con el formato de la plantilla de importación (decisión 87), para cargarlo en
 * otro equipo con Artículos → Importar o con `php artisan articulos:importar`.
 */
class ExportarArticulosAExcel extends Command
{
    protected $signature = 'articulos:exportar {archivo : Ruta del .xlsx que se va a crear} {--sin-existencias : Deja vacía la columna de existencia}';

    protected $description = 'Exporta el catálogo de artículos a un Excel listo para importarlo';

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');

        if (! str_ends_with(mb_strtolower($ruta), '.xlsx')) {
            $this->error('El archivo debe terminar en .xlsx');

            return self::FAILURE;
        }

        $contenido = Excel::raw(new CatalogoArticulos(! $this->option('sin-existencias')), \Maatwebsite\Excel\Excel::XLSX);

        if (@file_put_contents($ruta, $contenido) === false) {
            $this->error("No se pudo escribir «{$ruta}».");

            return self::FAILURE;
        }

        $this->info('Listo: '.Articulo::query()->count()." artículos en «{$ruta}».");

        return self::SUCCESS;
    }
}
