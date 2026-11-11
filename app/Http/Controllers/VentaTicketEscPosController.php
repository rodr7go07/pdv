<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\TicketEscPos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El ticket en comandos ESC/POS (en base64) para imprimirlo de un toque con RawBT desde Android.
 * Mismo permiso que el ticket impreso.
 */
class VentaTicketEscPosController extends Controller
{
    public function __invoke(Request $request, Venta $venta): Response
    {
        abort_unless($venta->ticketVisiblePor($request->user()), 403);

        return response(base64_encode(TicketEscPos::generar($venta)), 200, [
            'Content-Type' => 'text/plain; charset=US-ASCII',
            'Cache-Control' => 'no-store',
        ]);
    }
}
