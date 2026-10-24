@php
    $secciones = [
        'departamentos' => 'Departamentos',
        'unidades' => 'Unidades',
        'impuestos' => 'Impuestos',
    ];
    $actual = array_key_exists(request('seccion'), $secciones) ? request('seccion') : 'departamentos';
@endphp

<x-app-layout>
    <x-slot name="titulo">Catálogos</x-slot>

    <nav class="mb-6 flex gap-1 overflow-x-auto rounded-xl bg-anil-100/60 p-1" aria-label="Secciones de catálogos">
        @foreach ($secciones as $clave => $etiqueta)
            <a href="{{ route('catalogos', ['seccion' => $clave]) }}" wire:navigate
               @class([
                   'min-h-11 flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition',
                   'bg-white text-anil-900 shadow-sm' => $actual === $clave,
                   'text-anil-700 hover:text-anil-900' => $actual !== $clave,
               ])
               @if ($actual === $clave) aria-current="page" @endif>
                {{ $etiqueta }}
            </a>
        @endforeach
    </nav>

    <div class="max-w-3xl">
        @if ($actual === 'departamentos')
            <livewire:catalogos.departamentos />
        @elseif ($actual === 'unidades')
            <livewire:catalogos.unidades />
        @else
            <livewire:catalogos.impuestos />
        @endif
    </div>
</x-app-layout>
