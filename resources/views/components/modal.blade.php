@props(['nombre', 'titulo', 'maxWidth' => 'md'])

@php
    $ancho = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-2xl'][$maxWidth] ?? 'sm:max-w-md';
@endphp

{{-- Se abre con $dispatch('abrir-modal', { nombre: '...' }) y se cierra con 'cerrar-modal' --}}
<div x-data="{ abierto: false }"
     x-on:abrir-modal.window="if ($event.detail.nombre === '{{ $nombre }}') abierto = true"
     x-on:cerrar-modal.window="if (! $event.detail || ! $event.detail.nombre || $event.detail.nombre === '{{ $nombre }}') abierto = false"
     x-on:keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
     role="dialog" aria-modal="true" aria-label="{{ $titulo }}">

    <div x-show="abierto" x-transition.opacity @click="abierto = false" class="absolute inset-0 bg-anil-950/60"></div>

    <div x-show="abierto" x-trap.noscroll="abierto"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
         class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl {{ $ancho }}">
        <header class="flex items-center justify-between gap-3 border-b border-anil-100 px-5 py-4">
            <h2 class="text-lg font-extrabold text-anil-900">{{ $titulo }}</h2>
            <button type="button" @click="abierto = false" aria-label="Cerrar"
                    class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50">
                <x-phosphor-x-bold class="size-5" />
            </button>
        </header>
        <div class="overflow-y-auto px-5 py-5">
            {{ $slot }}
        </div>
    </div>
</div>
