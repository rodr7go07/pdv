{{-- Mensaje de éxito, error o aviso con su ícono. El margen lo pone quien lo usa: <x-alerta class="mb-5">Listo.</x-alerta> --}}
@props(['tono' => 'ok'])

@php
    [$colores, $icono] = [
        'ok' => ['bg-emerald-50 text-emerald-900 ring-emerald-200', 'check-circle'],
        'error' => ['bg-red-50 text-red-900 ring-red-200', 'warning'],
        'aviso' => ['bg-etiqueta/30 text-amber-950 ring-etiqueta/60', 'warning'],
    ][$tono];
@endphp

<div {{ $attributes->class(['flex items-start gap-2 rounded-lg px-4 py-3 text-sm font-medium ring-1', $colores]) }} role="{{ $tono === 'error' ? 'alert' : 'status' }}">
    {{ svg('phosphor-'.$icono.'-bold', 'mt-0.5 size-5 shrink-0') }}
    <span class="min-w-0">{{ $slot }}</span>
</div>
