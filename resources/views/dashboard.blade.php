@php
    $hora = now()->hour;
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $nombre = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
    $modulos = \App\Support\Menu::para(auth()->user())->reject(fn ($m) => in_array($m['ruta'], ['dashboard', 'profile', 'guia'], true));
    $verTablero = auth()->user()->can('reportes.ver');
    $miTurno = \App\Models\Turno::abiertoDe(auth()->user());
    $miResumen = $miTurno ? \App\Services\ResumenTurno::calcular($miTurno) : null;
@endphp

<x-app-layout>
    <x-slot name="titulo">{{ $saludo }}, {{ $nombre }}</x-slot>

    @if ($verTablero)
        <livewire:dashboard.metricas />
    @endif

    @if ($miTurno)
        <section class="{{ $verTablero ? 'mt-6' : '' }} rounded-xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)]" aria-labelledby="mi-turno">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="mi-turno" class="rotulo">Mi turno</h2>
                    <p class="font-display text-xl font-extrabold">{{ $miTurno->caja->nombre }}</p>
                    <p class="text-sm text-anil-700">Abierto desde las {{ $miTurno->abierto_at->format('H:i') }}</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-3xl font-extrabold">${{ number_format((float) $miResumen['ventas']['total'], 2) }}</p>
                    <p class="text-sm text-anil-700">{{ $miResumen['ventas']['cantidad'] }} {{ $miResumen['ventas']['cantidad'] === 1 ? 'venta' : 'ventas' }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="{{ $verTablero || $miTurno ? 'mt-8' : '' }}" aria-labelledby="modulos">
        <h2 id="modulos" class="rotulo mb-3">Tus accesos</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modulos as $modulo)
                <a href="{{ route($modulo['ruta']) }}" wire:navigate
                   class="group flex items-start gap-4 rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)] transition hover:-translate-y-0.5 hover:border-anil-300 hover:shadow-[0_4px_0_0_var(--color-anil-200)]">
                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-anil-900 text-rosa-400">
                        {{ svg('phosphor-'.$modulo['icono'].'-fill', 'size-6') }}
                    </span>
                    <span class="min-w-0">
                        <span class="block font-display text-lg font-extrabold leading-tight text-tinta [font-stretch:112%]">{{ $modulo['etiqueta'] }}</span>
                        <span class="mt-0.5 block text-sm text-anil-700">{{ $modulo['descripcion'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
</x-app-layout>
