<?php

namespace App\Http\Controllers;

use App\Services\ListaExistencias;
use App\Services\TicketEscPos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Hoja de existencias de los artículos elegidos en Inventario (decisión 83): en página para imprimir desde el
 * navegador, o en ESC/POS (base64) para la térmica por Bluetooth directo o RawBT, igual que el ticket.
 */
class ExistenciasImpresionController extends Controller
{
    public function pagina(Request $request): View
    {
        [$articulos, $ancho] = $this->datos($request);

        return view('inventario.existencias', [
            'texto' => ListaExistencias::texto($articulos, TicketEscPos::columnas($ancho)),
            'ancho' => $ancho,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }

    public function escpos(Request $request): Response
    {
        [$articulos, $ancho] = $this->datos($request);
        $texto = ListaExistencias::texto($articulos, TicketEscPos::columnas($ancho));

        return response(base64_encode(TicketEscPos::documento($texto)), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array{0: Collection, 1: int} */
    private function datos(Request $request): array
    {
        $ids = is_string($request->query('ids')) ? explode(',', $request->query('ids')) : [];
        $articulos = ListaExistencias::articulos($ids);
        abort_if($articulos->isEmpty(), 404, 'Elige al menos un artículo.');

        return [$articulos, ListaExistencias::anchoPapel($request->user())];
    }
}
