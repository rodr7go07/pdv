{{--
    Cuerpo del ticket de venta (sin <html>): lo comparten el ticket que se imprime en el navegador (`ventas.ticket`)
    y el PDF para compartir (`ventas.ticket-pdf`), para que ambos digan siempre lo mismo.
    Recibe lo de TicketVista::datos() y `$logo` (la dirección de la imagen o null).
--}}
@php
    $dinero = fn ($v) => '$'.number_format((float) $v, 2);
    $cant = fn ($v) => \App\Support\Numero::cantidad($v);
@endphp
<div class="centro">
    @if ($logo)
        <img src="{{ $logo }}" alt="" class="logo">
    @endif
    <div class="negrita grande">{{ $tienda->nombre_tienda }}</div>
    @if ($tienda->razon_social)<div>{{ $tienda->razon_social }}</div>@endif
    @if ($tienda->rfc)<div>RFC {{ $tienda->rfc }}</div>@endif
    @if ($tienda->direccion)<div>{{ $tienda->direccion }}</div>@endif
    @if ($tienda->telefono)<div>Tel. {{ $tienda->telefono }}</div>@endif
</div>

<hr class="linea">

<table>
    <tr><td>Folio</td><td class="der negrita">{{ $venta->folio }}</td></tr>
    <tr><td>Fecha</td><td class="der">{{ $venta->created_at->format('d/m/Y H:i') }}</td></tr>
    <tr><td>Caja</td><td class="der">{{ $venta->caja->nombre }}</td></tr>
    <tr><td>Atendió</td><td class="der">{{ $venta->cajero?->name ?? '—' }}</td></tr>
</table>

@if ($venta->estaCancelada())
    <div class="marca">VENTA CANCELADA</div>
@endif

<hr class="linea">

<table>
    @foreach ($venta->partidas as $p)
        <tr><td colspan="2" class="negrita">{{ $p->descripcion }}</td></tr>
        <tr>
            <td>{{ $cant($p->cantidad) }} {{ $p->unidad }} x {{ $dinero($p->precio_unitario) }}</td>
            <td class="der">{{ $dinero((string) \Brick\Math\BigDecimal::of((string) $p->total)->plus((string) $p->descuento)) }}</td>
        </tr>
        @if ((float) $p->descuento > 0)
            <tr><td>  Descuento</td><td class="der">-{{ $dinero($p->descuento) }}</td></tr>
        @endif
    @endforeach
</table>

<hr class="linea">

<table>
    <tr><td>Subtotal</td><td class="der">{{ $dinero($venta->subtotal) }}</td></tr>
    @foreach ($impuestos as $i)
        <tr><td>{{ $i['nombre'] }}</td><td class="der">{{ $dinero($i['monto']) }}</td></tr>
    @endforeach
    @if ((float) $venta->descuento > 0)
        <tr><td>Descuentos{{ $venta->descuento_efectivo ? ' (efectivo)' : '' }}</td><td class="der">-{{ $dinero($venta->descuento) }}</td></tr>
    @endif
    <tr class="negrita grande"><td>TOTAL</td><td class="der">{{ $dinero($venta->total) }}</td></tr>
</table>

<hr class="linea">

<table>
    @foreach ($venta->pagos as $pago)
        <tr><td>{{ \App\Enums\FormaPago::etiquetaDe($pago->forma) }}{{ $pago->referencia ? ' ('.$pago->referencia.')' : '' }}</td><td class="der">{{ $dinero($pago->monto) }}</td></tr>
    @endforeach
    @if ((float) $venta->efectivo_recibido > 0)
        <tr><td>Efectivo recibido</td><td class="der">{{ $dinero($venta->efectivo_recibido) }}</td></tr>
        <tr class="negrita"><td>Cambio</td><td class="der">{{ $dinero($venta->cambio) }}</td></tr>
    @endif
</table>

@if ($venta->devoluciones->isNotEmpty())
    <hr class="linea">
    <div class="negrita">Devoluciones</div>
    <table>
        @foreach ($venta->devoluciones as $d)
            <tr><td>{{ $d->folio }} · {{ $d->created_at->format('d/m/Y') }}</td><td class="der">-{{ $dinero($d->total) }}</td></tr>
        @endforeach
    </table>
@endif

<hr class="linea">
<div class="centro">
    <div>{{ $tienda->leyenda_ticket ?: 'Gracias por su compra' }}</div>
    <div style="margin-top:4px">Artículos: {{ $cant($venta->partidas->sum('cantidad')) }}</div>
</div>
