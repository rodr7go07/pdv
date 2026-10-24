@php
    $m = fn ($v) => '$'.number_format((float) $v, 2);
    $formas = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'credito' => 'A credito (sin cobrar)'];
@endphp
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $corte->etiqueta() }} · {{ $d['caja'] ?? '' }}</title>
    <style>
        @page { size: {{ $ancho }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { margin: 0 auto; padding: {{ $ancho === 58 ? '2mm 2mm 6mm' : '3mm 4mm 8mm' }}; width: {{ $ancho }}mm; background: #fff; color: #000; font: {{ $ancho === 58 ? '10.5px' : '12px' }}/1.4 'Courier New', ui-monospace, Consolas, monospace; }
        .centro { text-align: center; } .der { text-align: right; } .negrita { font-weight: bold; } .grande { font-size: 1.25em; }
        .linea { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
        .firma { margin-top: 22px; border-top: 1px solid #000; padding-top: 3px; text-align: center; }
        .acciones { position: fixed; top: 8px; right: 8px; font-family: system-ui, sans-serif; }
        .acciones button { padding: 8px 14px; border: 0; border-radius: 6px; background: #141a4a; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { html { background: none; } body { margin: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
    <div class="acciones"><button type="button" onclick="window.print()">Imprimir</button></div>

    <div class="centro">
        <div class="negrita grande">{{ $tienda->nombre_tienda }}</div>
        <div class="negrita">{{ strtoupper($corte->etiqueta()) }}</div>
    </div>

    <hr class="linea">
    <table>
        <tr><td>Caja</td><td class="der">{{ $d['caja'] ?? $corte->turno->caja->nombre }}</td></tr>
        <tr><td>Cajero</td><td class="der">{{ $d['cajero'] ?? $corte->turno->usuario->name }}</td></tr>
        <tr><td>Abierto</td><td class="der">{{ \Illuminate\Support\Carbon::parse($d['abierto_at'] ?? $corte->turno->abierto_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Corte</td><td class="der">{{ $corte->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Hizo el corte</td><td class="der">{{ $corte->usuario?->name }}</td></tr>
    </table>

    <hr class="linea">
    <div class="negrita">VENTAS</div>
    <table>
        <tr><td>Ventas</td><td class="der">{{ $d['ventas']['cantidad'] }}</td></tr>
        <tr><td>Subtotal</td><td class="der">{{ $m($d['ventas']['subtotal']) }}</td></tr>
        <tr><td>Impuestos</td><td class="der">{{ $m($d['ventas']['impuestos']) }}</td></tr>
        <tr><td>Descuentos</td><td class="der">{{ $m($d['ventas']['descuento']) }}</td></tr>
        <tr class="negrita"><td>Total vendido</td><td class="der">{{ $m($d['ventas']['total']) }}</td></tr>
    </table>

    <hr class="linea">
    <div class="negrita">FORMAS DE PAGO</div>
    <table>
        @foreach ($formas as $clave => $etiqueta)
            <tr><td>{{ $etiqueta }}</td><td class="der">{{ $m($d['pagos'][$clave] ?? 0) }}</td></tr>
        @endforeach
    </table>

    <hr class="linea">
    <div class="negrita">EFECTIVO EN CAJÓN</div>
    <table>
        <tr><td>Fondo inicial</td><td class="der">{{ $m($d['fondo_inicial']) }}</td></tr>
        <tr><td>+ Ventas en efectivo</td><td class="der">{{ $m($d['pagos']['efectivo'] ?? 0) }}</td></tr>
        @if ((float) ($d['abonos']['efectivo'] ?? 0) > 0)
            <tr><td>+ Abonos en efectivo</td><td class="der">{{ $m($d['abonos']['efectivo']) }}</td></tr>
        @endif
        <tr><td>+ Entradas</td><td class="der">{{ $m($d['entradas']) }}</td></tr>
        <tr><td>- Retiros</td><td class="der">{{ $m($d['retiros']) }}</td></tr>
        <tr><td>- Devoluciones ({{ $d['devoluciones']['cantidad'] }})</td><td class="der">{{ $m($d['devoluciones']['efectivo'] ?? $d['devoluciones']['total']) }}</td></tr>
        @if ($veEsperado || isset($d['efectivo_contado']))
            <tr class="negrita"><td>Efectivo esperado</td><td class="der">{{ $m($d['efectivo_esperado']) }}</td></tr>
        @endif
        @if (isset($d['efectivo_contado']))
            <tr><td>Efectivo contado</td><td class="der">{{ $m($d['efectivo_contado']) }}</td></tr>
            @php $dif = (float) $d['diferencia']; @endphp
            <tr class="negrita grande"><td>{{ $dif == 0 ? 'CAJA CUADRADA' : ($dif > 0 ? 'SOBRANTE' : 'FALTANTE') }}</td><td class="der">{{ $m(abs($dif)) }}</td></tr>
        @endif
    </table>

    @if ($d['canceladas']['cantidad'] > 0)
        <hr class="linea">
        <div>Canceladas: {{ $d['canceladas']['cantidad'] }} por {{ $m($d['canceladas']['total']) }}</div>
    @endif
    @if (! empty($d['notas']))
        <hr class="linea">
        <div>Notas: {{ $d['notas'] }}</div>
    @endif

    <div class="firma">Firma del cajero</div>

    @if ($imprimir)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
