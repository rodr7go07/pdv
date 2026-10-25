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

        // Igual que la pantalla: un valor que no sea número se ignora y se usa el predeterminado, en lugar de rechazar el enlace.
        $numero = fn (string $clave, int $predeterminado): int => is_string($request->query($clave)) && ctype_digit($request->query($clave)) && strlen($request->query($clave)) <= 9
            ? (int) $request->query($clave) : $predeterminado;

        $departamento = $numero('departamento', 0);
        $sugerencia = new SugerenciaCompra($numero('dias', 30), $numero('cobertura', 15), $departamento > 0 ? $departamento : null);

        $filas = $sugerencia->filas()->map(fn (array $f) => [
            $f['clave'], $f['descripcion'], $f['unidad'], (float) $f['existencia'], $f['minimo'] === null ? null : (float) $f['minimo'],
            (float) $f['vendido'], $f['dias_restantes'] === null ? null : (float) $f['dias_restantes'], (float) $f['sugerido'], (float) $f['costo'], (float) $f['importe'],
        ])->all();

        $encabezados = ['Clave', 'Artículo', 'Unidad', 'Existencia', 'Mínimo', 'Vendido ('.$sugerencia->diasVenta.' días)', 'Días que alcanza', 'Comprar', 'Costo unitario', 'Costo estimado'];

        return Excel::download(
            new ReporteExport('Sugerencia de compra', $encabezados, $filas, [0]),
            'sugerencia-de-compra-'.now()->toDateString().'.xlsx',
        );
    }
}
