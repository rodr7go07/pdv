<?php

namespace App\Http\Controllers;

use App\Exports\ReporteExport;
use App\Services\SugerenciaCompra;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exporta a Excel la sugerencia de compra: con los cambios de la pantalla (`editada`) o con los mismos filtros. */
class SugerenciaCompraExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('compras.ver'), 403);

        $editada = $request->boolean('editada') ? $request->session()->pull('sugerencia_editada') : null;

        if (is_array($editada) && now()->timestamp - (int) ($editada['en'] ?? 0) <= 600 && is_array($editada['filas'] ?? null)) {
            // Lo que la persona dejó en pantalla (con sus cambios), no un cálculo nuevo.
            $diasVenta = (int) ($editada['dias'] ?? 30);
            $origen = collect($editada['filas']);
        } else {
            // Igual que la pantalla: un valor que no sea número se ignora y se usa el predeterminado, en lugar de rechazar el enlace.
            $numero = fn (string $clave, int $predeterminado): int => is_string($request->query($clave)) && ctype_digit($request->query($clave)) && strlen($request->query($clave)) <= 9
                ? (int) $request->query($clave) : $predeterminado;

            $departamento = $numero('departamento', 0);
            $sugerencia = new SugerenciaCompra($numero('dias', 30), $numero('cobertura', 15), $departamento > 0 ? $departamento : null);
            $diasVenta = $sugerencia->diasVenta;
            $origen = $sugerencia->filas();
        }

        $filas = $origen->map(fn (array $f) => [
            $f['clave'], $f['descripcion'], $f['unidad'], (float) $f['existencia'], $f['minimo'] === null ? null : (float) $f['minimo'],
            (float) $f['vendido'], $f['dias_restantes'] === null ? null : (float) $f['dias_restantes'], (float) $f['sugerido'], (float) $f['costo'], (float) $f['importe'],
        ])->all();

        $encabezados = ['Clave', 'Artículo', 'Unidad', 'Existencia', 'Mínimo', 'Vendido ('.$diasVenta.' días)', 'Días que alcanza', 'Comprar', 'Costo unitario', 'Costo estimado'];

        return Excel::download(
            new ReporteExport('Sugerencia de compra', $encabezados, $filas, [0]),
            'sugerencia-de-compra-'.now()->toDateString().'.xlsx',
        );
    }
}
