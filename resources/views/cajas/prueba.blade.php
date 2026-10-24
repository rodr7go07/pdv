@php $caracteres = $ancho === 58 ? 32 : 48; @endphp
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Prueba de impresión · {{ $caja->nombre }}</title>
    <style>
        @page { size: {{ $ancho }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { margin: 0 auto; padding: {{ $ancho === 58 ? '2mm 2mm 6mm' : '3mm 4mm 8mm' }}; width: {{ $ancho }}mm; background: #fff; color: #000; font: {{ $ancho === 58 ? '10.5px' : '12px' }}/1.4 'Courier New', ui-monospace, Consolas, monospace; }
        .centro { text-align: center; } .negrita { font-weight: bold; } .grande { font-size: 1.3em; }
        .linea { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        .regla { border: 1px solid #000; padding: 2px; text-align: center; word-break: break-all; }
        .acciones { position: fixed; top: 8px; right: 8px; font-family: system-ui, sans-serif; }
        .acciones button { padding: 8px 14px; border: 0; border-radius: 6px; background: #141a4a; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { html { background: none; } body { margin: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
    <div class="acciones"><button type="button" onclick="window.print()">Imprimir prueba</button></div>

    <div class="centro">
        <div class="negrita grande">{{ $tienda->nombre_tienda }}</div>
        <div>PRUEBA DE IMPRESIÓN</div>
        <div>{{ $caja->nombre }} · papel de {{ $ancho }} mm</div>
    </div>
    <hr class="linea">
    <div class="regla">{{ substr(str_repeat('1234567890', 5), 0, $caracteres) }}</div>
    <p>Revisa que:</p>
    <p>1. La línea de arriba se vea completa, sin cortarse a los lados.</p>
    <p>2. El texto sea legible y no salga muy pequeño.</p>
    <p>3. La impresora corte el papel justo después de este mensaje.</p>
    <hr class="linea">
    <div class="centro">*** FIN DE LA PRUEBA ***</div>

    @if ($imprimir)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
