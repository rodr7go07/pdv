<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

        $venta->load(['partidas', 'pagos', 'caja', 'cajero:id,name', 'devoluciones']);

        // Desglose de impuestos del ticket: suma por nombre y tasa.
        $impuestos = $venta->partidas
            ->flatMap(fn ($p) => $p->impuestos_detalle ?? [])
            ->groupBy(fn ($i) => $i['nombre'])
            ->map(fn ($grupo, $nombre) => [
                'nombre' => $nombre,
                'monto' => (string) $grupo->reduce(fn (BigDecimal $c, $i) => $c->plus((string) $i['monto']), BigDecimal::zero())->toScale(2, RoundingMode::HalfUp),
            ])->values();

        return view('ventas.ticket', [
            'venta' => $venta,
            'tienda' => Configuracion::actual(),
            'ancho' => in_array($venta->caja->ancho_papel, [58, 80], true) ? $venta->caja->ancho_papel : 80,
            'impuestos' => $impuestos,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
