{{-- Tarjeta de indicador: una etiqueta, una cifra grande y, si hace falta, una nota debajo.
     Uso: <x-kpi etiqueta="Ventas" tono="aviso">{{ $cifra }}<x-slot:nota>texto</x-slot:nota></x-kpi>
     Con tag="button" (más wire:click) la tarjeta es un botón; con :activo="true" se marca como el filtro elegido. --}}
@props(['etiqueta', 'tono' => 'normal', 'tamano' => 'medio', 'tag' => 'div', 'activo' => null])

@php
    // [tarjeta, etiqueta, nota]
    $colores = [
        'normal' => ['border-anil-100 bg-white', '', 'text-anil-600'],
        'aviso' => ['border-etiqueta bg-etiqueta/20', '!text-amber-900', 'text-anil-600'],
        'peligro' => ['border-red-200 bg-red-50', '!text-red-800', 'text-anil-600'],
        'oscuro' => ['zona-fija border-fuerte bg-fuerte text-white', '!text-anil-300', 'text-anil-300'],
    ][$tono];
    $cifra = ['medio' => 'text-2xl sm:text-3xl', 'chico' => 'text-2xl', 'grande' => 'text-3xl'][$tamano];
    $esBoton = $tag === 'button';
@endphp

<{{ $tag }} @if ($esBoton && $activo !== null) aria-pressed="{{ $activo ? 'true' : 'false' }}" @endif
    {{ $attributes->class([
        'relative rounded-xl border p-4',
        $colores[0],
        'cursor-pointer transition hover:border-rosa-300 active:scale-[0.99]' => $esBoton,
        // Filtro elegido: orilla rosa gruesa y la marca «Filtrando» en la esquina.
        'border-rosa-500 ring-2 ring-rosa-500' => $activo,
    ]) }}>
    @if ($activo)
        <span class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-rosa-600 px-2 py-0.5 text-[0.6875rem] font-bold text-white">
            <x-phosphor-check-bold class="size-3" /> Filtrando
        </span>
    @endif
    <p @class(['rotulo', $colores[1], 'pr-20' => $activo])>{{ $etiqueta }}</p>
    <p class="mt-1 font-display {{ $cifra }} font-extrabold">{{ $slot }}</p>
    @isset($nota)
        <p {{ $nota->attributes->class(['text-xs', $colores[2]]) }}>{{ $nota }}</p>
    @endisset
</{{ $tag }}>
