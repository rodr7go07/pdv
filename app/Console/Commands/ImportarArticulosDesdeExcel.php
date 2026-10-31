<?php

namespace App\Console\Commands;

use App\Actions\Articulos\ImportarArticulos;
use App\Imports\LectorHoja;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Carga artículos desde un Excel o CSV sin pasar por el navegador (sin límite de tiempo de la web):
 * sirve para catálogos grandes. Hace lo mismo que Artículos → Importar: todo o nada.
 */
class ImportarArticulosDesdeExcel extends Command
{
    protected $signature = 'articulos:importar {archivo : Ruta del .xlsx, .xls o .csv} {--usuario=admin : Usuario que hace la carga (con sus permisos)}';

    protected $description = 'Importa artículos desde un archivo de Excel o CSV (todo o nada)';

    public function handle(ImportarArticulos $importar): int
    {
        $ruta = (string) $this->argument('archivo');

        if (! is_file($ruta) || ! is_readable($ruta)) {
            $this->error("No se encontró el archivo «{$ruta}».");

            return self::FAILURE;
        }

        $usuario = User::query()->where('usuario', mb_strtolower((string) $this->option('usuario')))->where('activo', true)->first();

        if (! $usuario || ! $usuario->can('articulos.importar')) {
            $this->error('El usuario no existe, está inactivo o no tiene permiso para importar artículos.');

            return self::FAILURE;
        }

        try {
            $filas = LectorHoja::leer($ruta);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($filas === []) {
            $this->error('El archivo no tiene artículos para importar.');

            return self::FAILURE;
        }

        $this->info(count($filas).' filas leídas. Importando…');

        $r = $importar($filas, $usuario, $usuario->can('articulos.editar_precio'));

        if ($r['errores'] !== []) {
            $this->error(count($r['errores']).' filas con error. No se guardó nada:');
            $this->table(['Fila', 'Clave', 'Qué corregir'], array_map(fn (array $e) => [$e['fila'], $e['clave'] ?: '—', implode(' | ', $e['mensajes'])], $r['errores']));

            return self::FAILURE;
        }

        $this->info("Listo: {$r['creados']} artículos nuevos, {$r['actualizados']} actualizados.");

        foreach ($r['avisos'] as $aviso) {
            $this->warn("Aviso: {$aviso}");
        }

        if ($r['sin_permiso_precios']) {
            $this->warn('Aviso: el usuario no puede cambiar costos y precios, así que esas columnas se ignoraron.');
        }

        return self::SUCCESS;
    }
}
