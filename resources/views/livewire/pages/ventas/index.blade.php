<?php

use App\Models\Caja;
use App\Models\User;
use App\Models\Venta;
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
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $caja = '';

    #[Url(except: '')]
    public string $cajero = '';

    #[Url(except: '')]
    public string $estado = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['ventas.cobrar', 'ventas.ver_todas']), 403);

        if ($this->desde === '' && $this->hasta === '') {
            $this->desde = $this->hasta = now()->toDateString();
        }
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function veTodas(): bool
    {
        return auth()->user()->can('ventas.ver_todas');
    }

    #[Computed]
    public function cajas()
    {
        return Caja::query()->withTrashed()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function cajeros()
    {
        return $this->veTodas ? User::query()->orderBy('name')->get(['id', 'name']) : collect();
    }

    private function consulta()
    {
        return Venta::query()
            ->when(! $this->veTodas, fn ($q) => $q->where('user_id', auth()->id()))
            ->when($this->buscar !== '', fn ($q) => $q->where('folio', 'like', '%'.addcslashes($this->buscar, '%_\\').'%'))
            ->when(\App\Support\Fecha::inicio($this->desde), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when(\App\Support\Fecha::fin($this->hasta), fn ($q, $h) => $q->where('created_at', '<=', $h))
            ->when($this->caja !== '', fn ($q) => $q->where('caja_id', $this->caja))
            ->when($this->veTodas && $this->cajero !== '', fn ($q) => $q->where('user_id', $this->cajero))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado));
    }

    #[Computed]
    public function resumen(): array
    {
        $base = $this->consulta();

        return [
            'ventas' => (clone $base)->where('estado', 'completada')->count(),
            'total' => (string) ((clone $base)->where('estado', 'completada')->sum('total')),
            'canceladas' => (clone $base)->where('estado', 'cancelada')->count(),
        ];
    }

    #[Computed]
    public function ventas()
    {
        return $this->consulta()->with(['caja:id,nombre', 'cajero:id,name'])->withCount('partidas')->latest()->paginate(20);
    }
}; ?>

<div>
    <x-slot name="titulo">Ventas</x-slot>
    <x-slot name="acciones">
        @can('ventas.cobrar')
            <a href="{{ route('pos') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-rosa-500 px-4 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none">
                <x-phosphor-cash-register-bold class="size-4" /> Ir a caja
            </a>
        @endcan
    </x-slot>

    @php $r = $this->resumen; @endphp

    <div class="mb-6 grid grid-cols-3 gap-3">
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <p class="rotulo">Ventas</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['ventas']) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <p class="rotulo">Vendido</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">${{ number_format((float) $r['total'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <p class="rotulo">Canceladas</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['canceladas']) }}</p>
        </div>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Folio" aria-label="Folio" autocomplete="off"
               class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <input wire:model.live="desde" type="date" aria-label="Desde" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <input wire:model.live="hasta" type="date" aria-label="Hasta" class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <x-select wire:model.live="caja" aria-label="Caja">
            <option value="">Todas las cajas</option>
            @foreach ($this->cajas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
        </x-select>
        @if ($this->veTodas)
            <x-select wire:model.live="cajero" aria-label="Cajero">
                <option value="">Todos los cajeros</option>
                @foreach ($this->cajeros as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </x-select>
        @endif
        <x-select wire:model.live="estado" aria-label="Estado">
            <option value="">Todas</option>
            <option value="completada">Completadas</option>
            <option value="cancelada">Canceladas</option>
        </x-select>
    </div>

    @php $lista = $this->ventas; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="receipt" titulo="No hay ventas en este periodo" texto="Cambia las fechas o los filtros para ver otras ventas." />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <ul class="divide-y divide-anil-50">
                @foreach ($lista as $v)
                    <li wire:key="v-{{ $v->id }}">
                        <a href="{{ route('ventas.ver', $v) }}" wire:navigate class="grid min-h-16 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-anil-50/60 sm:grid-cols-[7rem_minmax(0,1fr)_auto_6.5rem] sm:px-5">
                            <span class="font-mono text-sm font-semibold">{{ $v->folio }}</span>
                            <span class="order-last col-span-2 min-w-0 text-xs text-anil-600 sm:order-none sm:col-span-1">
                                {{ $v->created_at->format('d/m/Y H:i') }} · {{ $v->caja->nombre }} · {{ $v->cajero?->name ?? '—' }} · {{ $v->partidas_count }} {{ $v->partidas_count === 1 ? 'artículo' : 'artículos' }}
                            </span>
                            <span @class(['row-start-1 justify-self-end font-display text-lg font-extrabold sm:row-start-auto', 'text-anil-400 line-through' => $v->estaCancelada()])>${{ number_format((float) $v->total, 2) }}</span>
                            <span class="sm:justify-self-end">
                                <x-badge :tono="$v->estaCancelada() ? 'peligro' : 'ok'">{{ $v->estaCancelada() ? 'Cancelada' : 'Completada' }}</x-badge>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif
</div>
