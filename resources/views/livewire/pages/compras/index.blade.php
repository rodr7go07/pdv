<?php

use App\Models\Compra;
use App\Models\Proveedor;
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

    #[Url(except: '')]
    public string $proveedor = '';

    #[Url(except: '')]
    public string $estado = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        $this->authorize('compras.ver');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function compras()
    {
        return Compra::query()
            ->with('proveedor:id,nombre')
            ->withCount('partidas')
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('folio', 'like', '%'.addcslashes($this->buscar, '%_\\').'%')
                ->orWhere('factura', 'like', '%'.addcslashes($this->buscar, '%_\\').'%')))
            ->when($this->proveedor !== '', fn ($q) => $q->where('proveedor_id', $this->proveedor))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->when(\App\Support\Fecha::dia($this->desde), fn ($q, $d) => $q->where('fecha', '>=', $d->toDateString()))
            ->when(\App\Support\Fecha::dia($this->hasta), fn ($q, $h) => $q->where('fecha', '<=', $h->toDateString()))
            ->latest('fecha')->latest('id')
            ->paginate(15);
    }
}; ?>

<div>
    <x-slot name="titulo">Compras</x-slot>
    <x-slot name="acciones">
        @can('proveedores.administrar')
            <a href="{{ route('proveedores') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                <x-phosphor-truck-bold class="size-4" /> Proveedores
            </a>
        @endcan
        @can('compras.registrar')
            <a href="{{ route('compras.crear') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-rosa-500 px-4 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none">
                <x-phosphor-plus-bold class="size-4" /> Nueva compra
            </a>
        @endcan
    </x-slot>

    @if (session('exito'))
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ session('exito') }}
        </p>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Folio o factura" aria-label="Buscar por folio o factura" autocomplete="off"
               class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <x-select wire:model.live="proveedor" aria-label="Proveedor">
            <option value="">Todos los proveedores</option>
            @foreach ($this->proveedores as $p)
                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
            @endforeach
        </x-select>
        <x-select wire:model.live="estado" aria-label="Estado">
            <option value="">Todas</option>
            <option value="registrada">Registradas</option>
            <option value="cancelada">Canceladas</option>
        </x-select>
        <input wire:model.live="desde" type="date" aria-label="Desde" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <input wire:model.live="hasta" type="date" aria-label="Hasta" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
    </div>

    @php $lista = $this->compras; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="truck" titulo="No hay compras" :texto="$buscar || $proveedor || $estado || $desde || $hasta ? 'Ninguna compra cumple estos filtros.' : 'Registra la mercancía que recibes para actualizar existencias y costos.'" />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <ul class="divide-y divide-anil-50">
                @foreach ($lista as $c)
                    <li wire:key="co-{{ $c->id }}">
                        <a href="{{ route('compras.ver', $c) }}" wire:navigate class="grid min-h-16 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-anil-50/60 sm:grid-cols-[8rem_minmax(0,1fr)_auto_6rem] sm:px-5">
                            <span class="font-mono text-sm font-semibold sm:order-1">{{ $c->folio }}</span>
                            <span class="col-span-2 min-w-0 sm:order-2 sm:col-span-1">
                                <span class="block truncate font-semibold">{{ $c->proveedor->nombre }}</span>
                                <span class="text-xs text-anil-600">{{ $c->fecha->format('d/m/Y') }}{{ $c->factura ? ' · Factura '.$c->factura : '' }} · {{ $c->partidas_count }} {{ $c->partidas_count === 1 ? 'artículo' : 'artículos' }}</span>
                            </span>
                            <span class="row-start-1 justify-self-end font-display text-lg font-extrabold sm:order-3 sm:row-start-auto">${{ number_format((float) $c->total, 2) }}</span>
                            <span class="sm:order-4 sm:justify-self-end">
                                <x-badge :tono="$c->estaCancelada() ? 'peligro' : 'ok'">{{ $c->estaCancelada() ? 'Cancelada' : 'Registrada' }}</x-badge>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif
</div>
