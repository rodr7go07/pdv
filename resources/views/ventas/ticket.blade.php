@php
    $dinero = fn ($v) => '$'.number_format((float) $v, 2);
    $cant = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
    $formas = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'credito' => 'Crédito'];
@endphp
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Ticket {{ $venta->folio }}</title>
    <style>
        @page { size: {{ $ancho }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body {
            margin: 0 auto;
            padding: {{ $ancho === 58 ? '2mm 2mm 6mm' : '3mm 4mm 8mm' }};
            width: {{ $ancho }}mm;
            background: #fff;
            color: #000;
            font: {{ $ancho === 58 ? '10.5px' : '12px' }}/1.35 'Courier New', ui-monospace, Consolas, monospace;
            -webkit-print-color-adjust: exact;
        }
        .centro { text-align: center; }
        .der { text-align: right; }
        .negrita { font-weight: bold; }
        .grande { font-size: 1.25em; }
        .linea { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .logo { display: block; margin: 0 auto 4px; max-width: 40mm; max-height: 18mm; filter: grayscale(1) contrast(1.4); }
        .marca { margin: 6px 0; padding: 3px; border: 2px solid #000; text-align: center; font-weight: bold; letter-spacing: 2px; }
        .acciones { position: fixed; top: 8px; right: 8px; display: flex; gap: 6px; font-family: system-ui, sans-serif; }
        .acciones button { padding: 8px 14px; border: 0; border-radius: 6px; background: #141a4a; color: #fff; font-size: 14px; cursor: pointer; }
        @media print {
            html { background: none; }
            body { margin: 0; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>
    <div class="acciones"><button type="button" onclick="window.print()">Imprimir</button></div>

    <div class="centro">
        @if ($tienda->logoUrl())
            <img src="{{ $tienda->logoUrl() }}" alt="" class="logo">
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
                <td class="der">{{ $dinero(bcadd((string) $p->total, (string) $p->descuento, 2)) }}</td>
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
            <tr><td>Descuentos</td><td class="der">-{{ $dinero($venta->descuento) }}</td></tr>
        @endif
        <tr class="negrita grande"><td>TOTAL</td><td class="der">{{ $dinero($venta->total) }}</td></tr>
    </table>

    <hr class="linea">

    <table>
        @foreach ($venta->pagos as $pago)
            <tr><td>{{ $formas[$pago->forma] ?? $pago->forma }}{{ $pago->referencia ? ' ('.$pago->referencia.')' : '' }}</td><td class="der">{{ $dinero($pago->monto) }}</td></tr>
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

    @if ($imprimir)
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
    @endif
</body>
</html>
