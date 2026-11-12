<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Existencias</title>
    {{-- Hoja de existencias al ancho del papel de la caja (58 u 80 mm), con el mismo texto que sale por la térmica. --}}
    <style>
        @page { size: {{ $ancho }}mm auto; margin: 0; }
        html { background: #e5e7eb; }
        body {
            margin: 0 auto;
            padding: {{ $ancho === 58 ? '2mm 2mm 6mm' : '3mm 4mm 8mm' }};
            width: {{ $ancho }}mm;
            box-sizing: border-box;
            background: #fff;
            color: #000;
            -webkit-print-color-adjust: exact;
        }
        pre { margin: 0; font: {{ $ancho === 58 ? '10.5px' : '12px' }}/1.35 'Courier New', ui-monospace, Consolas, monospace; white-space: pre-wrap; }
        .acciones { position: fixed; top: 8px; right: 8px; font-family: system-ui, sans-serif; }
        .acciones button { padding: 8px 14px; border: 0; border-radius: 6px; background: #141a4a; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { html { background: none; } body { margin: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
    <div class="acciones"><button type="button" onclick="window.print()">Imprimir</button></div>
    <pre>{{ $texto }}</pre>
    @if ($imprimir)
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
    @endif
</body>
</html>
