<?php

use App\Models\Articulo;
use App\Models\Departamento;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'depto', except: '')]
    public string $departamento = '';

    /** todos | bajo | agotados */
    #[Url(except: 'todos')]
    public string $filtro = 'todos';

    public function mount(): void
    {
        $this->authorize('inventario.ver');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function departamentos()
    {
        return Departamento::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function verCostos(): bool
    {
        return auth()->user()->can('articulos.editar_precio');
    }

    /** Totales del inventario completo (no dependen de los filtros). */
    #[Computed]
    public function resumen(): array
    {
        $base = Articulo::query()->activos()->where('inventariable', true);

        return [
            'bajo' => (clone $base)->bajoMinimo()->count(),
            'agotados' => (clone $base)->where('existencia', '<=', 0)->count(),
            'valor' => $this->verCostos ? (string) ((clone $base)->where('existencia', '>', 0)->sum(\DB::raw('existencia * costo'))) : null,
            'articulos' => (clone $base)->count(),
        ];
    }

    #[Computed]
    public function articulos()
    {
        return Articulo::query()
            ->with(['departamento:id,nombre', 'unidad:id,abreviatura'])
            ->activos()
            ->where('inventariable', true)
            ->buscar($this->buscar)
            ->when($this->departamento !== '', fn ($q) => $q->where('departamento_id', $this->departamento))
            ->when($this->filtro === 'bajo', fn ($q) => $q->bajoMinimo())
            ->when($this->filtro === 'agotados', fn ($q) => $q->where('existencia', '<=', 0))
            ->orderBy('descripcion')
            ->paginate(20);
    }
}; ?>

<div>
    <x-slot name="titulo">Inventario</x-slot>
    <x-slot name="acciones">
        @can('inventario.conteo')
            <a href="{{ route('inventario.conteos') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                <x-phosphor-clipboard-text-bold class="size-4" /> Conteo físico
            </a>
        @endcan
        @can('inventario.ajustar')
            <a href="{{ route('inventario.ajustes') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                <x-phosphor-sliders-horizontal-bold class="size-4" /> Ajustes
            </a>
        @endcan
    </x-slot>

    @php $r = $this->resumen; @endphp

    {{-- Resumen --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'todos')" etiqueta="Artículos con inventario" tamano="grande" class="text-left">{{ number_format($r['articulos']) }}</x-kpi>
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'bajo')" etiqueta="Bajo el mínimo" tamano="grande" :tono="$r['bajo'] > 0 ? 'aviso' : 'normal'" class="text-left">{{ number_format($r['bajo']) }}</x-kpi>
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'agotados')" etiqueta="Agotados" tamano="grande" :tono="$r['agotados'] > 0 ? 'peligro' : 'normal'" class="text-left">{{ number_format($r['agotados']) }}</x-kpi>
        @if ($r['valor'] !== null)
            <x-kpi etiqueta="Valor del inventario">${{ number_format((float) $r['valor'], 2) }}<x-slot:nota class="mt-0.5">A costo vigente</x-slot:nota></x-kpi>
        @endif
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem_11rem]">
        <div class="flex gap-2" x-data x-on:codigo-escaneado.window="if ($event.detail.destino === 'buscar-inventario') { $wire.$set('buscar', $event.detail.codigo); }">
            <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Buscar por nombre o clave" aria-label="Buscar" autocomplete="off"
                   class="block w-full min-w-0 rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
            <x-boton-camara destino="buscar-inventario" />
        </div>
        <x-select wire:model.live="departamento" aria-label="Departamento">
            <option value="">Todos los departamentos</option>
            @foreach ($this->departamentos as $d)
                <option value="{{ $d->id }}">{{ $d->nombre }}</option>
            @endforeach
        </x-select>
        <x-select wire:model.live="filtro" aria-label="Mostrar">
            <option value="todos">Todos</option>
            <option value="bajo">Bajo el mínimo</option>
            <option value="agotados">Agotados</option>
        </x-select>
    </div>

    @php $lista = $this->articulos; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="package" titulo="No hay artículos para mostrar"
                 :texto="$filtro !== 'todos' ? 'Ningún artículo cumple este filtro.' : 'Aquí aparecen los artículos que llevan control de inventario.'" />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <table class="hidden w-full text-left text-sm md:table">
                <thead>
                    <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                        <th class="px-4 py-3 font-medium">Artículo</th>
                        <th class="hidden px-3 py-3 font-medium lg:table-cell">Departamento</th>
                        <th class="px-3 py-3 text-right font-medium">Existencia</th>
                        <th class="hidden px-3 py-3 text-right font-medium lg:table-cell">Mín. / Máx.</th>
                        <th class="px-4 py-3 text-right font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-anil-50">
                    @foreach ($lista as $a)
                        @php
                            $existencia = (float) $a->existencia;
                            $estado = $existencia <= 0 ? ['peligro', 'Agotado'] : ($a->estaBajoMinimo() ? ['aviso', 'Bajo el mínimo'] : ['ok', 'Bien']);
                        @endphp
                        <tr wire:key="inv-{{ $a->id }}" class="hover:bg-anil-50/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('inventario.kardex', $a) }}" wire:navigate class="font-semibold hover:text-rosa-600">{{ $a->descripcion }}</a>
                                <span class="block font-mono text-xs text-anil-600">{{ $a->clave }}</span>
                            </td>
                            <td class="hidden px-3 py-3 text-anil-700 lg:table-cell">{{ $a->departamento->nombre }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-display text-base font-extrabold">
                                {{ rtrim(rtrim((string) $a->existencia, '0'), '.') ?: '0' }}<span class="ml-1 text-xs font-medium text-anil-500">{{ $a->unidad->abreviatura }}</span>
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-3 text-right font-mono text-anil-700 lg:table-cell">
                                {{ $a->existencia_minima !== null ? rtrim(rtrim((string) $a->existencia_minima, '0'), '.') ?: '0' : '—' }} /
                                {{ $a->existencia_maxima !== null ? rtrim(rtrim((string) $a->existencia_maxima, '0'), '.') ?: '0' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right"><x-badge :tono="$estado[0]">{{ $estado[1] }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <ul class="divide-y divide-anil-50 md:hidden">
                @foreach ($lista as $a)
                    @php
                        $existencia = (float) $a->existencia;
                        $estado = $existencia <= 0 ? ['peligro', 'Agotado'] : ($a->estaBajoMinimo() ? ['aviso', 'Bajo el mínimo'] : ['ok', 'Bien']);
                    @endphp
                    <li wire:key="invm-{{ $a->id }}">
                        <a href="{{ route('inventario.kardex', $a) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3.5">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $a->descripcion }}</span>
                                <span class="mt-0.5 block font-mono text-xs text-anil-600">{{ $a->clave }}</span>
                                <x-badge :tono="$estado[0]" class="mt-1.5">{{ $estado[1] }}</x-badge>
                            </span>
                            <span class="shrink-0 text-right font-display text-xl font-extrabold">
                                {{ rtrim(rtrim((string) $a->existencia, '0'), '.') ?: '0' }}
                                <span class="block text-xs font-medium text-anil-500">{{ $a->unidad->abreviatura }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5">{{ $lista->links() }}</div>
    @endif

    <x-escaner-camara />
</div>
