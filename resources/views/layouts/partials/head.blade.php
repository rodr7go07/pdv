<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ ! empty($titulo) ? $titulo.' · ' : '' }}{{ \App\Models\Configuracion::actual()->nombre_tienda }}</title>

{{-- PWA --}}
<meta name="theme-color" content="#141a4a">
{{-- crossorigin="use-credentials" envía la cookie, así el manifest también se lee detrás de túneles que piden confirmar la visita (ngrok). --}}
<link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">
<link rel="icon" type="image/svg+xml" href="/icons/icono.svg">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ \App\Models\Configuracion::actual()->nombre_tienda }}">

@vite(['resources/css/app.css', 'resources/js/app.js'])
