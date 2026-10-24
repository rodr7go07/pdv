<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ticket de prueba para calibrar la impresora de una caja (márgenes, ancho y corte). */
class CajaPruebaController extends Controller
{
    public function __invoke(Request $request, Caja $caja): View
    {
        return view('cajas.prueba', [
            'caja' => $caja,
            'tienda' => Configuracion::actual(),
            'ancho' => in_array($caja->ancho_papel, [58, 80], true) ? $caja->ancho_papel : 80,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
