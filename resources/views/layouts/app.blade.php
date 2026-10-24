@php
    $tienda = \App\Models\Configuracion::actual();
    $usuario = auth()->user();
    $menu = \App\Support\Menu::para($usuario);
    $tituloPagina = isset($titulo) ? strip_tags((string) $titulo) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => $tituloPagina])
    </head>
    <body class="bg-papel antialiased">

        {{-- Barra lateral (PC y tablet horizontal) --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-anil-900 text-anil-100 lg:flex">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-6 pb-6 pt-7">
                @if ($tienda->logoUrl())
                    <img src="{{ $tienda->logoUrl() }}" alt="" class="size-10 rounded-lg bg-white object-contain p-1">
                @else
                    <x-application-logo class="size-10 shrink-0 text-rosa-500" />
                @endif
                <span class="min-w-0">
                    <span class="block truncate font-display text-lg font-extrabold leading-tight text-white [font-stretch:115%]">{{ $tienda->nombre_tienda }}</span>
                    <span class="rotulo !text-anil-300">Punto de venta</span>
                </span>
            </a>

            <nav class="flex-1 space-y-1 px-3" aria-label="Principal">
                @foreach ($menu as $item)
                    @php $activo = request()->routeIs($item['coincide']); @endphp
                    <a href="{{ route($item['ruta']) }}" wire:navigate
                       @class([
                           'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-[0.9375rem] font-medium transition-colors',
                           'bg-anil-800 text-white' => $activo,
                           'text-anil-200 hover:bg-anil-800/60 hover:text-white' => ! $activo,
                       ])
                       @if ($activo) aria-current="page" @endif>
                        @if ($activo)
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-rosa-500"></span>
                        @endif
                        {{ svg('phosphor-'.$item['icono'].($activo ? '-fill' : '-bold'), 'size-5 shrink-0 '.($activo ? 'text-rosa-400' : '')) }}
                        {{ $item['etiqueta'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-anil-800 p-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-anil-700 font-display text-sm font-extrabold uppercase text-white">
                        {{ \Illuminate\Support\Str::of($usuario->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $usuario->name }}</span>
                        <span class="rotulo !text-anil-300">{{ $usuario->nombreRol() ?? 'Sin rol' }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"
                                class="grid size-10 place-items-center rounded-lg text-anil-200 transition hover:bg-anil-800 hover:text-white">
                            {{ svg('phosphor-sign-out-bold', 'size-5') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Barra superior (teléfono y tablet vertical) --}}
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-anil-900 px-4 pt-[env(safe-area-inset-top)] lg:hidden">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex min-h-11 min-w-0 items-center gap-2.5">
                @if ($tienda->logoUrl())
                    <img src="{{ $tienda->logoUrl() }}" alt="" class="size-8 rounded-md bg-white object-contain p-0.5">
                @else
                    <x-application-logo class="size-8 shrink-0 text-rosa-500" />
                @endif
                <span class="truncate font-display text-base font-extrabold text-white [font-stretch:115%]">{{ $tienda->nombre_tienda }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Cerrar sesión" class="grid size-11 place-items-center rounded-lg text-anil-200 active:bg-anil-800">
                    {{ svg('phosphor-sign-out-bold', 'size-5') }}
                </button>
            </form>
        </header>

        {{-- Contenido --}}
        <main class="lg:pl-64">
            <div class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:px-10 lg:pb-12 lg:pt-10">
                @isset($titulo)
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 lg:mb-8">
                        <div>
                            <p class="rotulo">{{ now()->translatedFormat('l j \d\e F') }}</p>
                            <h1 class="mt-1 text-[1.75rem] font-extrabold leading-none text-tinta sm:text-3xl">{{ $titulo }}</h1>
                        </div>
                        @isset($acciones)
                            <div class="flex items-center gap-3">{{ $acciones }}</div>
                        @endisset
                    </div>
                @endisset

                {{ $slot }}
            </div>
        </main>

        {{-- Barra inferior (teléfono y tablet vertical) --}}
        <nav class="zona-segura-abajo fixed inset-x-0 bottom-0 z-30 border-t border-anil-800 bg-anil-900 lg:hidden" aria-label="Principal">
            <ul class="grid" style="grid-template-columns: repeat({{ $menu->count() }}, minmax(0, 1fr))">
                @foreach ($menu as $item)
                    @php $activo = request()->routeIs($item['coincide']); @endphp
                    <li>
                        <a href="{{ route($item['ruta']) }}" wire:navigate
                           @class([
                               'relative flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.6875rem] font-semibold',
                               'text-white' => $activo,
                               'text-anil-300' => ! $activo,
                           ])
                           @if ($activo) aria-current="page" @endif>
                            @if ($activo)
                                <span class="absolute inset-x-5 top-0 h-0.5 rounded-b-full bg-rosa-500"></span>
                            @endif
                            {{ svg('phosphor-'.$item['icono'].($activo ? '-fill' : '-bold'), 'size-6 '.($activo ? 'text-rosa-400' : '')) }}
                            {{ $item['etiqueta'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </body>
</html>
