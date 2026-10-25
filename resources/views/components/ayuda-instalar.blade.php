{{-- Pasos para instalar la app en iPhone y iPad (Safari no ofrece el botón de instalar). Se abre con $store.pwa.ayuda. --}}
<div x-data x-show="$store.pwa.ayuda" x-cloak x-on:keydown.escape.window="$store.pwa.ayuda = false"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Instalar la app">
    <div class="absolute inset-0 bg-anil-950/60" x-on:click="$store.pwa.ayuda = false"></div>
    <div class="relative w-full rounded-t-2xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:max-w-sm sm:rounded-2xl">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-display text-lg font-extrabold">Instalar la app en tu iPhone</h2>
            <button type="button" x-on:click="$store.pwa.ayuda = false" class="grid size-10 shrink-0 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
        </div>
        <ol class="mt-3 space-y-3 text-anil-900">
            <li class="flex items-start gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-rosa-500 text-sm font-extrabold text-white">1</span><span>Abre el sistema en <strong>Safari</strong> (no funciona desde otros navegadores).</span></li>
            <li class="flex items-start gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-rosa-500 text-sm font-extrabold text-white">2</span><span>Toca el botón <strong>Compartir</strong> <x-phosphor-export-bold class="inline size-4 align-text-bottom" /> de la barra de Safari.</span></li>
            <li class="flex items-start gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-rosa-500 text-sm font-extrabold text-white">3</span><span>Elige <strong>Agregar a pantalla de inicio</strong> y toca <strong>Agregar</strong>.</span></li>
        </ol>
        <button type="button" x-on:click="$store.pwa.ayuda = false" class="mt-5 min-h-11 w-full rounded-lg bg-anil-900 font-semibold text-white">Entendido</button>
    </div>
</div>
