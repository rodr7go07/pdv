@php($tienda = \App\Models\Configuracion::actual())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => 'Acceso'])
    </head>
    <body class="antialiased">
        <div class="grid min-h-dvh place-items-center bg-anil-900 px-4 py-10 [background-image:radial-gradient(circle,var(--color-anil-800)_1px,transparent_1px)] [background-size:22px_22px]">
            <div class="w-full max-w-sm">
                {{-- Ranura de la impresora: el ticket sale de aquí --}}
                <div class="relative z-10 -mb-2 h-4 rounded-full bg-anil-950 shadow-[inset_0_2px_4px_rgb(0_0_0/0.6),0_1px_0_var(--color-anil-700)]"></div>

                <div class="ticket animate-[imprimir_700ms_cubic-bezier(.2,.7,.2,1)_both] px-6 pt-9 sm:px-8">
                    <div class="flex flex-col items-center text-center">
                        @if ($tienda->logoUrl())
                            <img src="{{ $tienda->logoUrl() }}" alt="" class="size-14 object-contain">
                        @else
                            <x-application-logo class="size-12 text-rosa-500" />
                        @endif
                        <h1 class="mt-3 font-display text-2xl font-black uppercase leading-tight tracking-tight text-anil-900 [font-stretch:120%]">
                            {{ $tienda->nombre_tienda }}
                        </h1>
                        <p class="rotulo mt-1.5">Acceso al sistema</p>
                    </div>

                    <hr class="corte my-6">

                    {{ $slot }}

                    <hr class="corte mb-3 mt-7">
                    <p class="text-center font-mono text-[0.6875rem] uppercase tracking-widest text-anil-400">
                        {{ now()->format('d/m/Y') }}
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
