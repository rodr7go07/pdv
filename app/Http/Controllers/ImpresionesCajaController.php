<?php

namespace App\Http\Controllers;

use App\Enums\EstadoImpresion;
use App\Models\Caja;
use App\Models\Impresion;
use App\Models\Turno;
use App\Services\ColaImpresion;
use App\Services\TicketVista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Lo que la pantalla de Venta usa para atender los pedidos de impresión de su caja: consultar, reservar, avisar
 * que terminó y descartar. Solo la pantalla de la caja con turno abierto de quien pregunta ve y atiende los suyos.
 */
class ImpresionesCajaController extends Controller
{
    public function __construct(private readonly ColaImpresion $cola) {}

    public function pendientes(Request $request): JsonResponse
    {
        $caja = $this->caja($request);

        if (! $caja) {
            return response()->json(['modo' => Caja::PEDIDOS_SOLA, 'pendientes' => []]);
        }

        return response()->json([
            'modo' => $caja->impresion_pedidos,
            'pendientes' => $this->cola->pendientesDe($caja)->map(fn (Impresion $i) => ['id' => $i->id, 'folio' => $i->venta->folio])->values(),
        ]);
    }

    /** Reserva el pedido y devuelve la dirección (firmada, de pocos minutos) del ticket que hay que imprimir. */
    public function reservar(Request $request, Impresion $impresion): JsonResponse
    {
        $this->autorizar($request, $impresion);

        if (! $this->cola->reservar($impresion)) {
            return response()->json(['ok' => false, 'mensaje' => 'Otra pantalla ya lo está imprimiendo.'], 409);
        }

        return response()->json([
            'ok' => true,
            'folio' => $impresion->venta->folio,
            'url' => URL::temporarySignedRoute('impresiones.ticket', now()->addMinutes(3), ['impresion' => $impresion->id], absolute: false),
        ]);
    }

    public function confirmar(Request $request, Impresion $impresion): JsonResponse
    {
        $this->autorizar($request, $impresion);
        $this->cola->confirmar($impresion, $request->boolean('impreso'));

        return response()->json(['ok' => true]);
    }

    public function descartar(Request $request, Impresion $impresion): JsonResponse
    {
        $this->autorizar($request, $impresion);

        return response()->json(['ok' => $this->cola->cancelar($impresion)]);
    }

    /** El ticket de un pedido que esta pantalla reservó (la dirección va firmada: el cajero no necesita ver ventas ajenas). */
    public function ticket(Request $request, Impresion $impresion): View
    {
        $this->autorizar($request, $impresion);
        abort_unless($impresion->estado === EstadoImpresion::Imprimiendo, 410, 'Este pedido ya no está en espera de imprimirse.');

        return view('ventas.ticket', TicketVista::datos($impresion->venta, imprimir: true));
    }

    /** La caja del turno abierto de quien pregunta (su pantalla de Venta). */
    private function caja(Request $request): ?Caja
    {
        return Turno::abiertoDe($request->user())?->caja;
    }

    private function autorizar(Request $request, Impresion $impresion): void
    {
        abort_unless($this->caja($request)?->id === $impresion->caja_id, 403, 'Este pedido es de otra caja.');
    }
}
