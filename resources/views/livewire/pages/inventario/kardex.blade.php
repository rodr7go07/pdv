<?php

use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public Articulo $articulo;

    #[Url(except: '')]
    public string $tipo = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(Articulo $articulo): void
    {
        $this->authorize('inventario.ver');
        $this->articulo = $articulo->load(['unidad', 'departamento']);
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function verCostos(): bool
    {
        return auth()->user()->can('articulos.editar_precio');
    }

    #[Computed]
    public function movimientos()
    {
        return $this->articulo->movimientos()
            ->with('usuario:id,name', 'referencia')
            ->when($this->tipo !== '', fn ($q) => $q->where('tipo', $this->tipo))
            ->when(\App\Support\Fecha::inicio($this->desde), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when(\App\Support\Fecha::fin($this->hasta), fn ($q, $h) => $q->where('created_at', '<=', $h))
            ->paginate(25);
    }

    /** Enlace al documento que originó el movimiento, si existe. */
    public function enlace($mov): ?string
    {
        return match (true) {
            $mov->referencia instanceof \App\Models\Compra => auth()->user()->can('compras.ver') ? route('compras.ver', $mov->referencia) : null,
            $mov->referencia instanceof \App\Models\Conteo => auth()->user()->can('inventario.conteo') ? route('inventario.conteo', $mov->referencia) : null,
            default => null,
        };
    }

    public function referenciaTexto($mov): string
    {
        return match (true) {
            $mov->referencia instanceof \App\Models\Compra => $mov->referencia->folio,
            $mov->referencia instanceof \App\Models\Conteo => $mov->referencia->nombre,
            $mov->referencia !== null && isset($mov->referencia->folio) => $mov->referencia->folio,
            default => '',
        };
    }
}; ?>

<div>
    <x-slot name="titulo">Kardex</x-slot>
    <x-slot name="acciones">
        <x-enlace-volver :href="route('inventario.index')">Volver al inventario</x-enlace-volver>
    </x-slot>

    <x-card class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-xl font-extrabold leading-tight">{{ $articulo->descripcion }}</h2>
                <p class="mt-1 font-mono text-sm text-anil-700">{{ $articulo->clave }} · {{ $articulo->departamento->nombre }}</p>
            </div>
            <div class="text-right">
                <p class="rotulo">Existencia actual</p>
                <p class="font-display text-3xl font-extrabold">
                    {{ \App\Support\Numero::cantidad($articulo->existencia) }}
                    <span class="text-base font-medium text-anil-500">{{ $articulo->unidad->abreviatura }}</span>
                </p>
            </div>
        </div>
        @if ($this->verCostos)
            <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-anil-50 pt-4 text-sm">
                <div><dt class="rotulo">Costo vigente</dt><dd class="mt-0.5 font-mono font-semibold">${{ number_format((float) $articulo->costo, 2) }}</dd></div>
                <div><dt class="rotulo">Último costo</dt><dd class="mt-0.5 font-mono font-semibold">${{ number_format((float) $articulo->ultimo_costo, 2) }}</dd></div>
                <div><dt class="rotulo">Costo promedio</dt><dd class="mt-0.5 font-mono font-semibold">${{ number_format((float) $articulo->costo_promedio, 2) }}</dd></div>
            </dl>
        @endif
    </x-card>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-select wire:model.live="tipo" aria-label="Tipo de movimiento">
            <option value="">Todos los movimientos</option>
            @foreach (TipoMovimiento::cases() as $t)
                <option value="{{ $t->value }}">{{ $t->etiqueta() }}</option>
            @endforeach
        </x-select>
        <div>
            <input wire:model.live="desde" type="date" aria-label="Desde" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        </div>
        <div>
            <input wire:model.live="hasta" type="date" aria-label="Hasta" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        </div>
    </div>

    @php $lista = $this->movimientos; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="list-magnifying-glass" titulo="Sin movimientos" texto="Cuando compres, vendas o ajustes este artículo, cada cambio aparecerá aquí." />
    @else
        <div class="overflow-x-auto rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead>
                    <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                        <th class="px-4 py-3 font-medium">Fecha</th>
                        <th class="px-3 py-3 font-medium">Movimiento</th>
                        <th class="px-3 py-3 text-right font-medium">Cantidad</th>
                        <th class="px-3 py-3 text-right font-medium">Existencia</th>
                        @if ($this->verCostos)<th class="px-3 py-3 text-right font-medium">Costo</th>@endif
                        <th class="px-4 py-3 font-medium">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-anil-50">
                    @foreach ($lista as $m)
                        @php
                            $cantidad = (float) $m->cantidad;
                            $enlace = $this->enlace($m);
                            $ref = $this->referenciaTexto($m);
                        @endphp
                        <tr wire:key="mov-{{ $m->id }}">
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-anil-700">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2.5"><x-badge :tono="$cantidad >= 0 ? 'ok' : 'gris'">{{ $m->tipo->etiqueta() }}</x-badge></td>
                            <td @class(['whitespace-nowrap px-3 py-2.5 text-right font-mono font-semibold', 'text-emerald-700' => $cantidad > 0, 'text-red-700' => $cantidad < 0])>
                                {{ $cantidad > 0 ? '+' : '' }}{{ \App\Support\Numero::cantidad($m->cantidad) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono">{{ \App\Support\Numero::cantidad($m->existencia_resultante) }}</td>
                            @if ($this->verCostos)
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-anil-700">{{ $m->costo_unitario !== null ? '$'.number_format((float) $m->costo_unitario, 2) : '—' }}</td>
                            @endif
                            <td class="px-4 py-2.5 text-anil-700">
                                @if ($ref)
                                    @if ($enlace)<a href="{{ $enlace }}" wire:navigate class="font-mono font-semibold text-anil-900 underline">{{ $ref }}</a>@else<span class="font-mono">{{ $ref }}</span>@endif
                                @endif
                                @if ($m->motivo && $m->motivo !== 'conteo')<span class="capitalize">{{ $m->motivo }}</span>@endif
                                @if ($m->nota)<span class="block text-xs">{{ $m->nota }}</span>@endif
                                <span class="block text-xs text-anil-500">{{ $m->usuario?->name }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif
</div>
