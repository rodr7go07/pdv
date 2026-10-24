@php
    $hora = now()->hour;
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $nombre = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
    $modulos = \App\Support\Menu::para(auth()->user())->reject(fn ($m) => $m['ruta'] === 'dashboard');
@endphp

<x-app-layout>
    <x-slot name="titulo">{{ $saludo }}, {{ $nombre }}</x-slot>

    <section aria-labelledby="modulos">
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

    <section class="mt-8 rounded-xl border-2 border-dashed border-anil-200 p-6 text-center sm:p-8" aria-labelledby="proximos">
        <x-phosphor-cash-register-bold class="mx-auto size-8 text-anil-400" />
        <h2 id="proximos" class="mt-3 text-lg font-extrabold text-anil-900">Aquí aparecerán la caja y el inventario</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-anil-700">
            Por ahora puedes entrar y revisar los datos de la tienda. Los módulos de venta se activan conforme avance el sistema.
        </p>
    </section>
</x-app-layout>
