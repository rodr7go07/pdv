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

    @include('ventas.partials.ticket-cuerpo', ['logo' => $tienda->logoUrl()])

    @if ($imprimir)
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
    @endif
</body>
</html>
