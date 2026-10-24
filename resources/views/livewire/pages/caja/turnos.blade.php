<?php

use App\Models\Caja;
use App\Models\Turno;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $caja = '';

    #[Url(except: '')]
    public string $estado = '';

    public function mount(): void
    {
        $this->authorize('caja.supervisar');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function cajas()
    {
        return Caja::query()->withTrashed()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function turnos()
    {
        return Turno::query()->with(['caja', 'usuario:id,name'])
            ->when($this->desde !== '', fn ($q) => $q->whereDate('abierto_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('abierto_at', '<=', $this->hasta))
            ->when($this->caja !== '', fn ($q) => $q->where('caja_id', $this->caja))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->latest('abierto_at')->paginate(20);
    }
}; ?>

<div>
    <x-slot name="titulo">Turnos y cortes</x-slot>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input wire:model.live="desde" type="date" aria-label="Desde" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <input wire:model.live="hasta" type="date" aria-label="Hasta" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <x-select wire:model.live="caja" aria-label="Caja">
            <option value="">Todas las cajas</option>
            @foreach ($this->cajas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="estado" aria-label="Estado">
            <option value="">Todos</option>
            <option value="abierto">Abiertos</option>
            <option value="cerrado">Cerrados</option>
        </x-select>
    </div>

    @php $lista = $this->turnos; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="clock-counter-clockwise" titulo="No hay turnos" texto="Cuando los cajeros abran su caja, sus turnos aparecerán aquí." />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <ul class="divide-y divide-anil-50">
                @foreach ($lista as $t)
                    @php $d = $t->diferencia !== null ? (float) $t->diferencia : null; @endphp
                    <li wire:key="t-{{ $t->id }}">
                        <a href="{{ route('caja.turno', $t) }}" wire:navigate class="grid min-h-16 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-anil-50/60 sm:grid-cols-[minmax(0,1fr)_9rem_8rem_7rem] sm:px-5">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $t->caja->nombre }} · {{ $t->usuario->name }}</span>
                                <span class="text-xs text-anil-600">{{ $t->abierto_at->format('d/m/Y H:i') }}{{ $t->cerrado_at ? ' → '.$t->cerrado_at->format('H:i') : '' }}</span>
                            </span>
                            <span class="hidden text-right font-mono text-sm sm:block">{{ $t->efectivo_contado !== null ? '$'.number_format((float) $t->efectivo_contado, 2) : '—' }}</span>
                            <span class="text-right text-sm font-semibold">
                                @if ($d !== null)
                                    <span @class(['text-emerald-700' => $d == 0, 'text-amber-800' => $d > 0, 'text-red-700' => $d < 0])>{{ $d == 0 ? 'Cuadrada' : ($d > 0 ? 'Sobra $'.number_format($d, 2) : 'Falta $'.number_format(abs($d), 2)) }}</span>
                                @endif
                            </span>
                            <span class="justify-self-end"><x-badge :tono="$t->estaAbierto() ? 'ok' : 'gris'">{{ $t->estaAbierto() ? 'Abierto' : 'Cerrado' }}</x-badge></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif
</div>
