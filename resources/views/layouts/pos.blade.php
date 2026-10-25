<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => 'Venta'])
    </head>
    <body class="overflow-hidden bg-papel antialiased">
        {{ $slot }}
    </body>
</html>
