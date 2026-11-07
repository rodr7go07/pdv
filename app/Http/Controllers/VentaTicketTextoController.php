<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\TicketTexto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** El ticket como texto plano para compartirlo (WhatsApp, correo…). Mismo permiso que el ticket impreso. */
class VentaTicketTextoController extends Controller
{
    public function __invoke(Request $request, Venta $venta): Response
    {
        $usuario = $request->user();

        abort_unless($venta->ticketVisiblePor($usuario), 403);

        return response(TicketTexto::generar($venta), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
