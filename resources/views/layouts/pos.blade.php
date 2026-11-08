<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => 'Venta'])
    </head>
    <body class="overflow-hidden bg-papel antialiased">
        @include('layouts.partials.tema-inicial')
        {{ $slot }}
        <x-sin-conexion />
    </body>
</html>
