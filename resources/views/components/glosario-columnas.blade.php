{{-- Tabla pequeña con lo que significa cada columna de un reporte. Uso: <x-glosario-columnas :filas="['Neto' => 'Vendido menos devuelto…']" /> --}}
@props(['filas', 'titulo' => 'Qué significa cada columna'])

@if (count($filas))
    <aside {{ $attributes->class(['rounded-xl border border-anil-100 bg-white']) }} aria-label="{{ $titulo }}">
        <h3 class="border-b border-anil-100 px-4 py-3 text-sm font-extrabold text-anil-900">{{ $titulo }}</h3>
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="rotulo border-b border-anil-50 bg-anil-50/60"><th class="px-4 py-2 font-medium">Columna</th><th class="px-3 py-2 font-medium">Significado</th></tr>
            </thead>
            <tbody class="divide-y divide-anil-50 align-top">
                @foreach ($filas as $columna => $significado)
                    <tr><th scope="row" class="whitespace-nowrap px-4 py-2 text-left font-semibold text-anil-900">{{ $columna }}</th><td class="px-3 py-2 text-anil-700">{{ $significado }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </aside>
@endif
