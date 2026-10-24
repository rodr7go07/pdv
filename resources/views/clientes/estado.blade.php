@php $m = fn ($v) => '$'.number_format((float) $v, 2); @endphp
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Estado de cuenta · {{ $cliente->nombre }}</title>
    <style>
        @page { size: letter; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; padding: 16px; max-width: 190mm; color: #11153a; font: 13px/1.45 system-ui, -apple-system, 'Segoe UI', sans-serif; }
        h1 { margin: 0; font-size: 20px; } h2 { margin: 18px 0 6px; font-size: 14px; text-transform: uppercase; letter-spacing: .06em; color: #3a4cb0; }
        .cabecera { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #141a4a; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e4e8fa; text-align: left; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #3a4cb0; }
        .der { text-align: right; font-variant-numeric: tabular-nums; }
        .resumen { display: flex; gap: 24px; margin-top: 12px; }
        .resumen div { border: 1px solid #c9d1f4; border-radius: 6px; padding: 8px 12px; }
        .resumen strong { display: block; font-size: 17px; }
        .acciones { position: fixed; top: 8px; right: 8px; }
        .acciones button { padding: 8px 14px; border: 0; border-radius: 6px; background: #141a4a; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { .acciones { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="acciones"><button type="button" onclick="window.print()">Imprimir</button></div>

    <div class="cabecera">
        <div>
            <h1>{{ $tienda->nombre_tienda }}</h1>
            <div>{{ collect([$tienda->razon_social, $tienda->rfc ? 'RFC '.$tienda->rfc : null, $tienda->direccion, $tienda->telefono ? 'Tel. '.$tienda->telefono : null])->filter()->implode(' · ') }}</div>
        </div>
        <div style="text-align:right">
            <strong>ESTADO DE CUENTA</strong><br>
            {{ now()->translatedFormat('j \d\e F \d\e Y') }}
        </div>
    </div>

    <h2>Cliente</h2>
    <div><strong>{{ $cliente->nombre }}</strong></div>
    <div>{{ collect([$cliente->telefono, $cliente->correo, $cliente->direccion, $cliente->rfc ? 'RFC '.$cliente->rfc : null])->filter()->implode(' · ') }}</div>

    <div class="resumen">
        <div>Saldo actual <strong>{{ $m($cliente->saldo) }}</strong></div>
        <div>Límite de crédito <strong>{{ $m($cliente->limite_credito) }}</strong></div>
        <div>Disponible <strong>{{ $m($cliente->disponible()) }}</strong></div>
    </div>

    <h2>Movimientos</h2>
    <table>
        <thead><tr><th>Fecha</th><th>Concepto</th><th class="der">Cargo</th><th class="der">Abono</th><th class="der">Saldo</th></tr></thead>
        <tbody>
            @forelse ($movimientos as $mov)
                <tr>
                    <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $mov->etiqueta() }}{{ $mov->venta ? ' · '.$mov->venta->folio : '' }}{{ $mov->forma ? ' · '.ucfirst($mov->forma) : '' }}</td>
                    <td class="der">{{ $mov->esCargo() ? $m($mov->monto) : '' }}</td>
                    <td class="der">{{ ! $mov->esCargo() ? $m($mov->monto) : '' }}</td>
                    <td class="der">{{ $m($mov->saldo_resultante) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin movimientos.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($imprimir)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
