<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Estado de cuenta de un cliente listo para imprimir (hoja carta). */
class EstadoCuentaController extends Controller
{
    public function __invoke(Request $request, Cliente $cliente): View
    {
        abort_unless($request->user()->can('clientes.ver'), 403);

        return view('clientes.estado', [
            'cliente' => $cliente,
            'tienda' => Configuracion::actual(),
            'movimientos' => $cliente->movimientos()->with(['venta:id,folio', 'usuario:id,name'])->reorder()->orderBy('created_at')->orderBy('id')->limit(500)->get(),
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
