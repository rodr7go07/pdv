@php($tienda = \App\Models\Configuracion::actual())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => 'Acceso'])
    </head>
    <body class="antialiased">
        @include('layouts.partials.tema-inicial')
        <div class="relative flex min-h-dvh flex-col overflow-x-clip bg-papel">
            {{-- Toldo de la tiendita: rayas rosa y blanco con orilla de olanes --}}
            <div class="toldo h-12 shrink-0 sm:h-16 apaisado:h-10" aria-hidden="true"></div>

            <div class="mx-auto grid w-full max-w-5xl flex-1 grid-cols-[minmax(0,1fr)] content-center items-center gap-8 px-4 pb-10 pt-6 sm:pt-10 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-16 lg:px-8 apaisado:grid-cols-2 apaisado:items-start apaisado:gap-8 apaisado:pt-6">
                {{-- Rótulo pintado de la fachada --}}
                {{-- El tamaño del nombre se ajusta para que su palabra más larga quepa completa en el ancho disponible
                     (cada letra mide ~0.86 de su tamaño en esta letra ancha; 0.95 deja margen). Nunca se corta una palabra. --}}
                @php($letras = max(1, ...array_map('mb_strlen', preg_split('/\s+/u', trim($tienda->nombre_tienda)) ?: [''])))
                <header class="aparecer @container text-center lg:text-left apaisado:sticky apaisado:top-6 apaisado:text-left">
                    <p class="text-sm font-semibold text-rosa-600">Punto de venta</p>
                    <h1 style="--letras: {{ $letras }}"
                        class="mt-3 font-display text-[clamp(1.25rem,calc(100cqi/(var(--letras)*0.95)),var(--tam))] font-black uppercase leading-[0.95] tracking-tight text-tinta [--tam:2.5rem] [font-stretch:125%] [hyphens:manual] [overflow-wrap:break-word] sm:[--tam:3.5rem] lg:[--tam:4.5rem] apaisado:[--tam:2.5rem]">
                        {{ $tienda->nombre_tienda }}
                    </h1>
                    <div class="mx-auto mt-5 h-1.5 w-20 bg-rosa-500 lg:mx-0 apaisado:mx-0"></div>
                </header>

                <div class="mx-auto w-full max-w-sm">
                    {{-- Impresora de tickets: el ticket sale de la ranura --}}
                    <div class="relative z-10 rounded-xl bg-anil-950 px-4 pb-2 pt-3">
                        <div class="flex items-center justify-between px-1 pb-2">
                            <span class="text-[0.6875rem] font-semibold text-anil-400">Caja</span>
                            <span class="foco size-2 rounded-full bg-ok" aria-hidden="true"></span>
                        </div>
                        <div class="h-2.5 rounded-full bg-black/60 shadow-[inset_0_2px_4px_rgb(0_0_0/0.8)]"></div>
                    </div>

                    <div class="ticket mx-3 border border-anil-100 -mt-1.5 animate-[imprimir_700ms_cubic-bezier(.2,.7,.2,1)_350ms_both] px-6 pt-8 sm:px-8">
                        <div class="flex items-center gap-3">
                            @if ($tienda->logoUrl())
                                <img src="{{ $tienda->logoUrl() }}" alt="" class="size-11 object-contain">
                            @else
                                <x-application-logo class="size-10 text-rosa-500" />
                            @endif
                            <div>
                                <p class="font-display text-lg font-extrabold leading-tight text-anil-900 [font-stretch:112%]">Bienvenido</p>
                                <p class="rotulo mt-0.5">Acceso al sistema</p>
                            </div>
                        </div>

                        <hr class="corte my-6">

                        {{ $slot }}

                        <hr class="corte mb-4 mt-7">
                        <div class="codigo-barras mx-auto h-9 w-44 text-anil-900" aria-hidden="true"></div>
                        <p class="mt-2 text-center font-mono text-[0.6875rem] uppercase tracking-widest text-anil-400">
                            {{ now()->format('d/m/Y') }}
                        </p>
                    </div>

                    <button type="button" x-data x-show="$store.pwa.puedeInstalar" x-cloak x-on:click="$store.pwa.instalar()"
                            class="mx-auto mt-6 flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                        <x-phosphor-download-simple-bold class="size-5 text-rosa-600" /> Instalar app
                    </button>
                </div>
            </div>
        </div>
        <x-ayuda-instalar />
    </body>
</html>
