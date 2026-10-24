@props(['tono' => 'gris'])

@php
    $tonos = [
        'gris' => 'bg-anil-50 text-anil-700 ring-anil-100',
        'ok' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'aviso' => 'bg-etiqueta/30 text-amber-900 ring-etiqueta/60',
        'peligro' => 'bg-red-50 text-red-800 ring-red-200',
        'rosa' => 'bg-rosa-50 text-rosa-700 ring-rosa-100',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset', $tonos[$tono] ?? $tonos['gris']]) }}>
    {{ $slot }}
</span>
