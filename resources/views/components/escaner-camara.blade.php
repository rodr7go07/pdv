{{-- Ventana para escanear un código de barras con la cámara. Se abre con $dispatch('abrir-camara', { destino: '...' })
     y, al leer, avisa con 'codigo-escaneado' ({ destino, codigo }). Ponla una sola vez por pantalla. --}}
<div x-data="escanerCamara"
     x-on:abrir-camara.window="abrir($event.detail?.destino)"
     x-on:keydown.escape.window="abierto && cerrar()"
     x-show="abierto" x-cloak :data-escaner-abierto="abierto ? '' : null"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Escanear con la cámara">
    <div class="absolute inset-0 bg-anil-950/70" x-on:click="cerrar()"></div>

    <div class="relative flex max-h-[96dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-anil-950 text-white shadow-2xl sm:max-w-md sm:rounded-2xl">
        <header class="flex shrink-0 items-center justify-between px-4 py-3">
            <h2 class="text-lg font-extrabold">Escanear con la cámara</h2>
            <button type="button" x-on:click="cerrar()" class="grid size-10 place-items-center rounded-lg text-anil-200 hover:bg-anil-800" aria-label="Cerrar la cámara"><x-phosphor-x-bold class="size-5" /></button>
        </header>
        {{-- max-h: con el teléfono acostado el video no debe empujar los botones fuera de la pantalla --}}
        <div class="relative aspect-[4/3] max-h-[50dvh] min-h-0 w-full bg-black">
            <video x-ref="video" class="size-full object-cover" playsinline muted></video>
            <div x-show="!error" class="pointer-events-none absolute inset-x-[12%] top-1/2 h-[34%] -translate-y-1/2 rounded-xl border-2 border-rosa-500/90 shadow-[0_0_0_9999px_rgb(0_0_0/0.35)]"></div>
            <p x-show="iniciando" aria-live="polite" class="absolute inset-0 grid place-items-center text-sm text-anil-200">Abriendo la cámara…</p>
            <p x-show="error" x-text="error" x-cloak class="absolute inset-0 grid place-items-center px-6 text-center text-sm font-medium text-red-200" role="alert"></p>
            <button type="button" x-show="hayLinterna" x-cloak x-on:click="alternarLinterna()" :aria-pressed="linterna" aria-label="Linterna"
                    :class="linterna ? 'bg-rosa-500 text-white' : 'bg-black/50 text-white'"
                    class="absolute bottom-3 right-3 grid size-11 place-items-center rounded-full">
                <x-phosphor-flashlight-bold class="size-5" />
            </button>
        </div>
        <div class="flex shrink-0 items-center justify-between gap-3 px-4 py-3">
            <p class="text-sm text-anil-200" x-text="error ? 'No se pudo leer con la cámara.' : 'Apunta al código de barras: se escribe solo.'"></p>
            <button type="button" x-on:click="cerrar()" class="min-h-11 shrink-0 rounded-lg border border-anil-700 px-4 font-semibold text-white hover:bg-anil-800">Cancelar</button>
        </div>
    </div>
</div>
