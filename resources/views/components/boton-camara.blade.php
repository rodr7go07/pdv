{{-- Botón de cámara junto a un campo de código. Solo se ve en teléfono y tableta (menos de 1024 px). --}}
@props(['destino'])

<button type="button" x-on:click="$dispatch('abrir-camara', { destino: '{{ $destino }}' })" aria-label="Escanear con la cámara" title="Escanear con la cámara"
        class="grid w-12 shrink-0 place-items-center rounded-lg bg-anil-900 text-white shadow-[0_3px_0_0_var(--color-anil-950)] active:translate-y-[3px] active:shadow-none lg:hidden">
    <x-phosphor-camera-bold class="size-5" />
</button>
