@php
    $tienda = \App\Models\Configuracion::actual();
    $usuario = auth()->user();
    $menu = \App\Support\Menu::para($usuario);
    $barra = \App\Support\Menu::barraMovil($menu);
    $tituloPagina = isset($titulo) ? strip_tags((string) $titulo) : null;
    $activoEn = fn (array $item) => request()->routeIs($item['coincide']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['titulo' => $tituloPagina])
        @livewireStyles
    </head>
    <body class="bg-papel antialiased">
        @include('layouts.partials.tema-inicial')

        {{-- Barra lateral (PC y tablet horizontal) --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-anil-100 bg-white text-anil-800 lg:flex">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-6 pb-5 pt-7">
                @if ($tienda->logoUrl())
                    <img src="{{ $tienda->logoUrl() }}" alt="" class="size-10 rounded-lg border border-anil-100 bg-white object-contain p-1">
                @else
                    <x-application-logo class="size-10 shrink-0 text-rosa-500" />
                @endif
                <span class="min-w-0">
                    <span class="block truncate font-display text-lg font-extrabold leading-tight text-tinta [font-stretch:115%]">{{ $tienda->nombre_tienda }}</span>
                    <span class="rotulo">Punto de venta</span>
                </span>
            </a>

            <nav class="flex-1 space-y-4 overflow-y-auto px-3 pb-4" aria-label="Principal">
                @foreach (\App\Support\Menu::GRUPOS as $grupo)
                    @php $items = $menu->where('grupo', $grupo); @endphp
                    @if ($items->isNotEmpty())
                        <div>
                            @if (! $loop->first)
                                <p class="rotulo mb-1 px-3 !text-anil-500">{{ $grupo }}</p>
                            @endif
                            <div class="space-y-0.5">
                                @foreach ($items as $item)
                                    @php $activo = $activoEn($item); @endphp
                                    <a href="{{ route($item['ruta']) }}" wire:navigate
                                       @class([
                                           'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-[0.9375rem] font-medium transition-colors',
                                           'bg-rosa-50 text-rosa-700' => $activo,
                                           'text-anil-700 hover:bg-anil-50 hover:text-tinta' => ! $activo,
                                       ])
                                       @if ($activo) aria-current="page" @endif>
                                        {{ svg('phosphor-'.$item['icono'].($activo ? '-fill' : '-bold'), 'size-5 shrink-0 '.($activo ? 'text-rosa-600' : 'text-anil-500')) }}
                                        {{ $item['etiqueta'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

            <div class="border-t border-anil-100 p-4">
                <x-tema-interruptor />
                <button type="button" x-data x-show="$store.pwa.puedeInstalar" x-cloak x-on:click="$store.pwa.instalar()"
                        class="mb-3 flex min-h-11 w-full items-center gap-3 rounded-lg border border-anil-200 px-3 text-sm font-semibold text-anil-800 transition hover:bg-anil-50">
                    {{ svg('phosphor-download-simple-bold', 'size-5 shrink-0 text-rosa-600') }} Instalar app
                </button>
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-rosa-50 font-display text-sm font-extrabold uppercase text-rosa-700">
                        {{ \Illuminate\Support\Str::of($usuario->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-tinta">{{ $usuario->name }}</span>
                        <span class="rotulo">{{ $usuario->nombreRol() ?? 'Sin rol' }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"
                                class="grid size-10 place-items-center rounded-lg text-anil-600 transition hover:bg-anil-50 hover:text-tinta">
                            {{ svg('phosphor-sign-out-bold', 'size-5') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Barra superior (teléfono y tablet vertical) --}}
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-anil-100 bg-white px-4 pt-[env(safe-area-inset-top)] lg:hidden">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex min-h-11 min-w-0 items-center gap-2.5">
                @if ($tienda->logoUrl())
                    <img src="{{ $tienda->logoUrl() }}" alt="" class="size-8 rounded-md border border-anil-100 bg-white object-contain p-0.5">
                @else
                    <x-application-logo class="size-8 shrink-0 text-rosa-500" />
                @endif
                <span class="truncate font-display text-base font-extrabold text-tinta [font-stretch:115%]">{{ $tienda->nombre_tienda }}</span>
            </a>
            <div class="flex items-center gap-1">
            <x-tema-interruptor variante="icono" class="active:bg-anil-100" />
            <button type="button" x-data x-show="$store.pwa.puedeInstalar" x-cloak x-on:click="$store.pwa.instalar()" aria-label="Instalar app" title="Instalar app"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-anil-800 active:bg-anil-100">
                {{ svg('phosphor-download-simple-bold', 'size-5 text-rosa-600') }} Instalar
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Cerrar sesión" class="grid size-11 place-items-center rounded-lg text-anil-600 active:bg-anil-100">
                    {{ svg('phosphor-sign-out-bold', 'size-5') }}
                </button>
            </form>
            </div>
        </header>

        {{-- Contenido --}}
        <main class="lg:pl-64">
            <div class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:px-10 lg:pb-12 lg:pt-10">
                @isset($titulo)
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 lg:mb-8">
                        <div class="min-w-0">
                            <p class="rotulo">{{ now()->translatedFormat('l j \d\e F') }}</p>
                            <h1 class="mt-1 text-[1.75rem] font-extrabold leading-none text-tinta sm:text-3xl">{{ $titulo }}</h1>
                        </div>
                        @isset($acciones)
                            <div class="flex flex-wrap items-center gap-3">{{ $acciones }}</div>
                        @endisset
                    </div>
                @endisset

                {{ $slot }}
            </div>
        </main>

        {{-- Barra inferior (teléfono y tablet vertical) --}}
        <div x-data="{ mas: false }" class="lg:hidden" x-on:keydown.escape.window="mas = false">
            <nav class="zona-segura-abajo fixed inset-x-0 bottom-0 z-40 border-t border-anil-100 bg-white" aria-label="Principal">
                <ul class="grid" style="grid-template-columns: repeat({{ $barra['visibles']->count() + ($barra['mas'] ? 1 : 0) }}, minmax(0, 1fr))">
                    @foreach ($barra['visibles'] as $item)
                        @php $activo = $activoEn($item); @endphp
                        <li>
                            <a href="{{ route($item['ruta']) }}" wire:navigate
                               @class([
                                   'relative flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.6875rem] font-semibold',
                                   'text-rosa-700' => $activo,
                                   'text-anil-600' => ! $activo,
                               ])
                               @if ($activo) aria-current="page" @endif>
                                @if ($activo)
                                    <span class="absolute inset-x-5 top-0 h-0.5 bg-rosa-500"></span>
                                @endif
                                {{ svg('phosphor-'.$item['icono'].($activo ? '-fill' : '-bold'), 'size-6 '.($activo ? 'text-rosa-600' : '')) }}
                                {{ $item['etiqueta'] }}
                            </a>
                        </li>
                    @endforeach
                    @if ($barra['mas'])
                        <li>
                            <button type="button" x-on:click="mas = true" class="flex min-h-14 w-full flex-col items-center justify-center gap-0.5 text-[0.6875rem] font-semibold text-anil-600"
                                    aria-haspopup="dialog" :aria-expanded="mas">
                                <x-phosphor-list-bold class="size-6" /> Más
                            </button>
                        </li>
                    @endif
                </ul>
            </nav>

            @if ($barra['mas'])
                <div x-show="mas" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Todas las secciones">
                    <div x-show="mas" x-transition.opacity x-on:click="mas = false" class="absolute inset-0 bg-anil-950/60"></div>
                    <div x-show="mas" x-trap.noscroll="mas"
                         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-8 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                         class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-2xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl">
                        <div class="sticky top-0 flex items-center justify-between border-b border-anil-100 bg-white px-5 py-3">
                            <p class="font-display text-lg font-extrabold">Todas las secciones</p>
                            <button type="button" x-on:click="mas = false" aria-label="Cerrar" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50">
                                <x-phosphor-x-bold class="size-5" />
                            </button>
                        </div>
                        <div class="space-y-5 px-4 py-4">
                            @foreach (\App\Support\Menu::GRUPOS as $grupo)
                                @php $items = $menu->where('grupo', $grupo); @endphp
                                @if ($items->isNotEmpty())
                                    <div>
                                        <p class="rotulo mb-2 px-1">{{ $grupo }}</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($items as $item)
                                                @php $activo = $activoEn($item); @endphp
                                                <a href="{{ route($item['ruta']) }}" wire:navigate x-on:click="mas = false"
                                                   @class(['flex min-h-14 items-center gap-3 rounded-xl border px-3.5 text-sm font-semibold', 'border-rosa-200 bg-rosa-50 text-rosa-700' => $activo, 'border-anil-100 bg-white text-anil-900' => ! $activo])>
                                                    {{ svg('phosphor-'.$item['icono'].'-bold', 'size-5 shrink-0') }}
                                                    {{ $item['etiqueta'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <x-sin-conexion />
        <x-ayuda-instalar />
        @livewireScripts
    </body>
</html>
