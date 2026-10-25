{{-- Enlace «Volver a…» que va en el encabezado de la pantalla. Uso: <x-enlace-volver :href="route('compras.index')">Volver a compras</x-enlace-volver> --}}
<a wire:navigate {{ $attributes->class(['inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white']) }}>
    <x-phosphor-arrow-left-bold class="size-4" /> {{ $slot }}
</a>
