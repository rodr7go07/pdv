<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\TicketVista;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ticket de venta listo para imprimir (58 u 80 mm) con el CSS de impresión del navegador. */
class VentaTicketController extends Controller
{
    public function __invoke(Request $request, Venta $venta): View
    {
        $usuario = $request->user();

        // Ver cualquier ticket requiere permiso; el cajero solo reimprime los suyos.
        abort_unless(
            $usuario->can('ventas.ver_todas') || ($usuario->can('ventas.cobrar') && $venta->user_id === $usuario->id),
            403,
        );

        return view('ventas.ticket', TicketVista::datos($venta, $request->boolean('imprimir')));
    }
}
