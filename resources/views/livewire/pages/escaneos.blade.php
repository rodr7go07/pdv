<?php

use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Models\Escaneo;
use App\Models\User;
use App\Support\Fecha;
use Illuminate\Database\Eloquent\Builder;
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
    public string $origen = '';

    #[Url(except: '')]
    public string $medio = '';

    /** '' = todos | encontrados | sin_articulo */
    #[Url(except: '')]
    public string $resultado = '';

    #[Url(except: '')]
    public string $usuario = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        $this->authorize('reportes.ver');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset('buscar', 'origen', 'medio', 'resultado', 'usuario', 'desde', 'hasta');
        $this->resetPage();
    }

    #[Computed]
    public function usuarios()
    {
        return User::query()->whereIn('id', Escaneo::query()->select('user_id')->distinct()->whereNotNull('user_id'))->orderBy('name')->get(['id', 'name']);
    }

    private function base(): Builder
    {
        $texto = trim($this->buscar);
        $inicio = Fecha::inicio($this->desde);
        $fin = Fecha::fin($this->hasta);

        return Escaneo::query()
            ->when($texto !== '', fn ($q) => $q->where('codigo', 'like', '%'.addcslashes($texto, '%_\\').'%'))
            ->when(OrigenEscaneo::tryFrom($this->origen), fn ($q, $o) => $q->where('origen', $o->value))
            ->when(MedioEscaneo::tryFrom($this->medio), fn ($q, $m) => $q->where('medio', $m->value))
            ->when($this->resultado === 'encontrados', fn ($q) => $q->whereNot(fn ($w) => $w->sinArticulo()))
            ->when($this->resultado === 'sin_articulo', fn ($q) => $q->sinArticulo())
            ->when($this->usuario !== '' && ctype_digit($this->usuario), fn ($q) => $q->where('user_id', (int) $this->usuario))
            ->when($inicio, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($fin, fn ($q, $d) => $q->where('created_at', '<=', $d));
    }

    /** @return array{total: int, sin_articulo: int, camara: int} */
    #[Computed]
    public function resumen(): array
    {
        return [
            'total' => $this->base()->count(),
            'sin_articulo' => $this->base()->sinArticulo()->count(),
            'camara' => $this->base()->where('medio', MedioEscaneo::Camara->value)->count(),
        ];
    }

    #[Computed]
    public function escaneos()
    {
        $lista = $this->base()->with(['usuario:id,name', 'articulo:id,clave,descripcion,deleted_at'])->latest('created_at')->latest('id')->paginate(25);

        // Un código escaneado antes de dar de alta el artículo muestra el artículo que ya existe hoy.
        $actuales = Escaneo::articulosActuales($lista->getCollection()->whereNull('articulo_id')->pluck('codigo'));
        $lista->getCollection()->each(fn (Escaneo $e) => $e->setRelation('articuloActual', $actuales->get($e->codigo)));

        return $lista;
    }
}; ?>

<div>
    <x-slot name="titulo">Códigos escaneados</x-slot>

    @php
        $r = $this->resumen;
        $lista = $this->escaneos;
        $hayFiltros = $buscar !== '' || $origen !== '' || $medio !== '' || $resultado !== '' || $usuario !== '' || $desde !== '' || $hasta !== '';
        $campo = 'block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none';
    @endphp

    <p class="mb-5 max-w-3xl text-sm text-anil-700">
        Todo código que se escanea o se captura en el sistema queda anotado aquí: en la venta, en compras, en inventario y al dar de alta artículos.
        Con la cámara se registra cada lectura. Con lector USB o teclado se registra lo que se envía con Enter, sin distinguir uno del otro.
        Los códigos <strong>sin artículo</strong> son productos que todavía no tienes dados de alta (si lo das de alta después, aquí aparece con su nombre). Se borran solos después de 6 meses.
    </p>

    <div class="mb-5 grid grid-cols-3 gap-3">
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <p class="rotulo">Escaneos</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['total']) }}</p>
        </div>
        <div @class(['rounded-xl border p-4 shadow-[0_2px_0_0_var(--color-anil-100)]', 'border-etiqueta bg-etiqueta/20' => $r['sin_articulo'] > 0, 'border-anil-100 bg-white' => $r['sin_articulo'] === 0])>
            <p class="rotulo !text-amber-900">Sin artículo</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['sin_articulo']) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <p class="rotulo">Con cámara</p>
            <p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['camara']) }}</p>
        </div>
    </div>

    <div class="mb-5 grid grid-cols-[minmax(0,1fr)] gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Buscar un código" aria-label="Buscar un código" autocomplete="off" spellcheck="false" class="{{ $campo }} font-mono">
        <x-select wire:model.live="origen" aria-label="Pantalla">
            <option value="">Todas las pantallas</option>
            @foreach (OrigenEscaneo::cases() as $o)
                <option value="{{ $o->value }}">{{ $o->etiqueta() }}</option>
            @endforeach
        </x-select>
        <x-select wire:model.live="medio" aria-label="Cómo se escaneó">
            <option value="">Lector y cámara</option>
            @foreach (MedioEscaneo::cases() as $m)
                <option value="{{ $m->value }}">{{ $m->etiqueta() }}</option>
            @endforeach
        </x-select>
        <x-select wire:model.live="resultado" aria-label="Resultado">
            <option value="">Con y sin artículo</option>
            <option value="encontrados">Con artículo</option>
            <option value="sin_articulo">Sin artículo (por dar de alta)</option>
        </x-select>
        <x-select wire:model.live="usuario" aria-label="Quién escaneó">
            <option value="">Todas las personas</option>
            @foreach ($this->usuarios as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </x-select>
        <input wire:model.live="desde" type="date" aria-label="Desde" class="{{ $campo }}">
        <input wire:model.live="hasta" type="date" aria-label="Hasta" class="{{ $campo }}">
        @if ($hayFiltros)
            <button type="button" wire:click="limpiar" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                <x-phosphor-x-bold class="size-4" /> Quitar filtros
            </button>
        @endif
    </div>

    @if ($lista->isEmpty())
        <x-empty icono="barcode" titulo="No hay escaneos" :texto="$hayFiltros ? 'Ningún escaneo cumple estos filtros.' : 'Cuando alguien escanee un código aparecerá aquí.'" />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <div class="hidden grid-cols-[9.5rem_minmax(0,1fr)_minmax(0,1.2fr)_9rem_11rem] gap-x-4 border-b border-anil-100 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-anil-600 md:grid">
                <span>Fecha</span><span>Código</span><span>Artículo</span><span>Pantalla</span><span>Persona</span>
            </div>
            <ul class="divide-y divide-anil-50">
                @foreach ($lista as $e)
                    <li wire:key="esc-{{ $e->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 md:grid-cols-[9.5rem_minmax(0,1fr)_minmax(0,1.2fr)_9rem_11rem] md:px-5">
                        <p class="font-mono text-sm font-semibold md:order-2">{{ $e->codigo }}</p>
                        <p class="row-start-1 justify-self-end text-xs text-anil-600 md:order-1 md:row-start-auto md:justify-self-start md:text-sm">{{ $e->created_at->format('d/m/Y H:i') }}</p>
                        <div class="col-span-2 min-w-0 md:order-3 md:col-span-1">
                            @if ($e->articulo)
                                <p class="truncate text-sm font-semibold">{{ $e->articulo->descripcion }}</p>
                            @elseif ($e->articuloActual)
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="min-w-0 truncate text-sm font-semibold">{{ $e->articuloActual->descripcion }}</span>
                                    <x-badge>Ya dado de alta</x-badge>
                                </p>
                            @elseif ($e->encontrado)
                                <p class="text-sm text-anil-600">Artículo eliminado</p>
                            @else
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-badge tono="aviso">Sin artículo</x-badge>
                                    @can('articulos.crear')
                                        <a href="{{ route('articulos.crear', ['clave' => $e->codigo]) }}" wire:navigate class="text-sm font-semibold text-rosa-600 underline">Dar de alta</a>
                                    @endcan
                                </p>
                            @endif
                        </div>
                        <p class="col-span-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-anil-700 md:order-4 md:col-span-1 md:block">
                            <span class="font-semibold text-anil-900">{{ $e->origen->etiqueta() }}</span>
                            <x-badge :tono="$e->medio === MedioEscaneo::Camara ? 'rosa' : 'gris'">{{ $e->medio->etiqueta() }}</x-badge>
                        </p>
                        <p class="col-span-2 truncate text-xs text-anil-700 md:order-5 md:col-span-1 md:text-sm">{{ $e->usuario?->name ?? '—' }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif
</div>
