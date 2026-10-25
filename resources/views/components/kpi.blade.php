{{-- Tarjeta de indicador: una etiqueta, una cifra grande y, si hace falta, una nota debajo.
     Uso: <x-kpi etiqueta="Ventas" tono="aviso">{{ $cifra }}<x-slot:nota>texto</x-slot:nota></x-kpi>
     Con tag="button" (más wire:click) la tarjeta es un botón. --}}
@props(['etiqueta', 'tono' => 'normal', 'tamano' => 'medio', 'sombra' => true, 'tag' => 'div'])

@php
    // [tarjeta, etiqueta, nota]
    $colores = [
        'normal' => ['border-anil-100 bg-white', '', 'text-anil-600'],
        'aviso' => ['border-etiqueta bg-etiqueta/20', '!text-amber-900', 'text-anil-600'],
        'peligro' => ['border-red-200 bg-red-50', '!text-red-800', 'text-anil-600'],
        'oscuro' => ['border-anil-900 bg-anil-900 text-white', '!text-anil-300', 'text-anil-300'],
    ][$tono];
    $cifra = ['medio' => 'text-2xl sm:text-3xl', 'chico' => 'text-2xl', 'grande' => 'text-3xl'][$tamano];
@endphp

<{{ $tag }} {{ $attributes->class(['rounded-xl border p-4', 'shadow-[0_2px_0_0_var(--color-anil-100)]' => $sombra, $colores[0]]) }}>
    <p @class(['rotulo', $colores[1]])>{{ $etiqueta }}</p>
    <p class="mt-1 font-display {{ $cifra }} font-extrabold">{{ $slot }}</p>
    @isset($nota)
        <p {{ $nota->attributes->class(['text-xs', $colores[2]]) }}>{{ $nota }}</p>
    @endisset
</{{ $tag }}>
