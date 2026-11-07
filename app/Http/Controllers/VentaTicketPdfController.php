<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\TicketPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** El ticket como PDF para compartirlo o guardarlo. Mismo permiso que el ticket impreso. */
class VentaTicketPdfController extends Controller
{
    public function __invoke(Request $request, Venta $venta): Response
    {
        abort_unless($venta->ticketVisiblePor($request->user()), 403);

        $archivo = 'ticket-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $venta->folio).'.pdf';

        return response(TicketPdf::generar($venta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="'.$archivo.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
