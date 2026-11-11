{{--
    Interruptor del modo oscuro (sol/luna). El estado vive en el store `tema` (resources/js/tema.js).
    `variante="fila"` es la fila con texto de la barra lateral; `variante="icono"` es solo el botón (barras del teléfono y de la caja).
--}}
@props(['variante' => 'fila'])

<button type="button" x-data x-on:click="$store.tema.alternar()" x-bind:aria-pressed="$store.tema.oscuro"
        x-bind:aria-label="$store.tema.oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        x-bind:title="$store.tema.oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        {{ $attributes->class([
            'flex items-center rounded-lg text-anil-800 transition',
            'mb-2 min-h-10 w-full gap-3 border border-anil-200 px-3 text-sm font-semibold hover:bg-anil-50' => $variante === 'fila',
            'size-11 shrink-0 justify-center text-anil-600 hover:bg-anil-50' => $variante === 'icono',
        ]) }}>
    <x-phosphor-moon-bold x-show="! $store.tema.oscuro" class="size-5 shrink-0 text-rosa-600" />
    <x-phosphor-sun-bold x-show="$store.tema.oscuro" x-cloak class="size-5 shrink-0 text-rosa-600" />
    @if ($variante === 'fila')
        <span x-text="$store.tema.oscuro ? 'Modo claro' : 'Modo oscuro'">Modo oscuro</span>
    @endif
</button>
