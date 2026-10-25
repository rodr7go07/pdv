<?php

namespace App\Http\Controllers;

use App\Exports\ReporteExport;
use App\Services\SugerenciaCompra;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exporta a Excel la sugerencia de compra con los mismos filtros de la pantalla. */
class SugerenciaCompraExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('compras.ver'), 403);

        $datos = $request->validate([
            'dias' => ['nullable', 'integer'],
            'cobertura' => ['nullable', 'integer'],
            'departamento' => ['nullable', 'integer'],
        ]);

        $sugerencia = new SugerenciaCompra((int) ($datos['dias'] ?? 30), (int) ($datos['cobertura'] ?? 15), isset($datos['departamento']) ? (int) $datos['departamento'] : null);

        $filas = $sugerencia->filas()->map(fn (array $f) => [
            $f['clave'], $f['descripcion'], $f['unidad'], (float) $f['existencia'], $f['minimo'] === null ? null : (float) $f['minimo'],
            (float) $f['vendido'], $f['dias_restantes'] === null ? null : (float) $f['dias_restantes'], (float) $f['sugerido'], (float) $f['costo'], (float) $f['importe'],
        ])->all();

        $encabezados = ['Clave', 'Artículo', 'Unidad', 'Existencia', 'Mínimo', 'Vendido ('.$sugerencia->diasVenta.' días)', 'Días que alcanza', 'Comprar', 'Costo unitario', 'Costo estimado'];

        return Excel::download(
            new ReporteExport('Sugerencia de compra', $encabezados, $filas),
            'sugerencia-de-compra-'.now()->toDateString().'.xlsx',
        );
    }
}
