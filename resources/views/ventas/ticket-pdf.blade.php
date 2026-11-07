{{-- Ticket de venta para el PDF (dompdf). El contenido es el mismo del ticket impreso: ver ventas/partials/ticket-cuerpo. --}}
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $venta->folio }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: {{ $ancho === 58 ? '2mm 2mm 3mm' : '3mm 4mm 4mm' }};
            color: #000;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: {{ $ancho === 58 ? '7.2pt' : '8.4pt' }};
            line-height: 1.2;
        }
        .centro { text-align: center; }
        .der { text-align: right; }
        .negrita { font-weight: bold; }
        .grande { font-size: 1.25em; }
        .linea { border: 0; border-top: 1px dashed #000; margin: 4px 0; height: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .logo { margin: 0 auto 4px; max-width: 40mm; max-height: 18mm; }
        .marca { margin: 6px 0; padding: 3px; border: 2px solid #000; text-align: center; font-weight: bold; letter-spacing: 2px; }
    </style>
</head>
<body>
    @include('ventas.partials.ticket-cuerpo')
</body>
</html>
