<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Corte;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Impresión de un corte (X o Z) con las cifras tal como estaban al hacerlo. */
class CorteController extends Controller
{
    public function __invoke(Request $request, Corte $corte): View
    {
        $usuario = $request->user();
        $corte->load(['turno.caja', 'turno.usuario:id,name', 'usuario:id,name']);

        abort_unless($usuario->can('caja.supervisar') || $corte->turno->user_id === $usuario->id, 403);

        $ancho = $corte->turno->caja->ancho_papel;

        return view('caja.corte', [
            'corte' => $corte,
            'd' => $corte->datos,
            'tienda' => Configuracion::actual(),
            'ancho' => in_array($ancho, [58, 80], true) ? $ancho : 80,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
