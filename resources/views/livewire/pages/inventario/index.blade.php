<?php

use App\Models\Articulo;
use App\Actions\Inventario\RegistrarEntradaRapida;
use App\Models\Departamento;
use App\Services\ListaExistencias;
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

    /** Artículos marcados para imprimir su hoja de existencias (se conservan al cambiar de página o de filtro). */
    public array $seleccion = [];

    public function updating(string $propiedad): void
    {
        if ($propiedad !== 'seleccion') {
            $this->resetPage();
        }
    }

    public function updatedSeleccion(): void
    {
        $this->seleccion = collect($this->seleccion)->map(fn ($id) => (string) (int) $id)->filter(fn ($id) => $id !== '0')
            ->unique()->take(ListaExistencias::MAXIMO)->values()->all();
    }

    /** Marca todos los artículos que muestra el filtro actual (todas las páginas), hasta el máximo de la hoja. */
    public function seleccionarFiltrados(): void
    {
        $ids = $this->consulta()->limit(ListaExistencias::MAXIMO)->pluck('articulos.id')->map(fn ($id) => (string) $id);
        $this->seleccion = collect($this->seleccion)->merge($ids)->unique()->take(ListaExistencias::MAXIMO)->values()->all();
    }

    public function quitarSeleccion(): void
    {
        $this->seleccion = [];
    }

    // ─── Botón «+»: meter mercancía de un artículo (decisión 84) ───────────────────
    public ?int $entradaId = null;

    public string $entradaCantidad = '';

    public ?string $entradaExito = null;

    #[Computed]
    public function puedeEntrada(): bool
    {
        return auth()->user()->can('compras.registrar');
    }

    #[Computed]
    public function articuloEntrada(): ?Articulo
    {
        return $this->entradaId ? Articulo::query()->with('unidad:id,abreviatura')->find($this->entradaId) : null;
    }

    public function abrirEntrada(int $id): void
    {
        $this->authorize('compras.registrar');
        $this->entradaId = $id;
        $this->entradaCantidad = '';
        $this->entradaExito = null;
        $this->resetErrorBag('entradaCantidad');
        $this->dispatch('abrir-modal', nombre: 'entrada');
    }

    public function guardarEntrada(RegistrarEntradaRapida $registrar): void
    {
        $this->authorize('compras.registrar');
        $this->resetErrorBag('entradaCantidad');
        $articulo = $this->articuloEntrada;
        if (! $articulo) {
            return;
        }

        try {
            $registrar([['articulo_id' => $articulo->id, 'cantidad' => trim($this->entradaCantidad)]], auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('entradaCantidad', collect($e->errors())->flatten()->first() ?? 'Revisa la cantidad.');

            return;
        }

        $this->entradaExito = 'Entraron '.\App\Support\Numero::cantidad(trim($this->entradaCantidad)).' '.$articulo->unidad->abreviatura.' de '.$articulo->descripcion.'.';
        $this->entradaId = null;
        $this->dispatch('cerrar-modal', nombre: 'entrada');
        unset($this->resumen, $this->articulos);
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
            // Sin traslape: un artículo agotado cuenta como agotado, no también como «bajo el mínimo».
            'bajo' => (clone $base)->bajoMinimoConExistencia()->count(),
            'agotados' => (clone $base)->agotados()->count(),
            'valor' => $this->verCostos ? (string) ((clone $base)->where('existencia', '>', 0)->sum(\DB::raw('existencia * costo'))) : null,
            'articulos' => (clone $base)->count(),
        ];
    }

    private function consulta()
    {
        return Articulo::query()
            ->activos()
            ->where('inventariable', true)
            ->buscar($this->buscar)
            ->when($this->departamento !== '', fn ($q) => $q->where('departamento_id', $this->departamento))
            ->when($this->filtro === 'bajo', fn ($q) => $q->bajoMinimoConExistencia())
            ->when($this->filtro === 'agotados', fn ($q) => $q->agotados())
            ->orderBy('descripcion');
    }

    #[Computed]
    public function articulos()
    {
        return $this->consulta()->with(['departamento:id,nombre', 'unidad:id,abreviatura'])->paginate(20);
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
        @can('compras.registrar')
            <a href="{{ route('inventario.entrada') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-fuerte px-4 text-sm font-semibold text-white hover:opacity-90">
                <x-phosphor-plus-circle-bold class="size-4" /> Entrada de mercancía
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
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'todos')" :activo="$filtro === 'todos'" etiqueta="Artículos con inventario" tamano="grande" class="text-left">{{ number_format($r['articulos']) }}</x-kpi>
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'bajo')" :activo="$filtro === 'bajo'" etiqueta="Bajo el mínimo" tamano="grande" :tono="$r['bajo'] > 0 ? 'aviso' : 'normal'" class="text-left">{{ number_format($r['bajo']) }}</x-kpi>
        <x-kpi tag="button" type="button" wire:click="$set('filtro', 'agotados')" :activo="$filtro === 'agotados'" etiqueta="Agotados" tamano="grande" :tono="$r['agotados'] > 0 ? 'peligro' : 'normal'" class="text-left">{{ number_format($r['agotados']) }}</x-kpi>
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

    @if ($entradaExito)
        <x-alerta class="mb-4">{{ $entradaExito }}</x-alerta>
    @endif

    {{-- Hoja de existencias: marcar artículos e imprimirlos en la térmica (decisión 83) --}}
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-anil-700">Marca artículos para imprimir sus existencias.</span>
        @if ($lista->isNotEmpty())
            <button type="button" wire:click="seleccionarFiltrados" class="font-semibold text-rosa-600 underline underline-offset-2">Marcar todos los de este filtro</button>
        @endif
    </div>

    @if ($lista->isEmpty())
        <x-empty icono="package" titulo="No hay artículos para mostrar"
                 :texto="$filtro !== 'todos' ? 'Ningún artículo cumple este filtro.' : 'Aquí aparecen los artículos que llevan control de inventario.'" />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white">
            <table class="hidden w-full text-left text-sm md:table">
                <thead>
                    <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                        <th class="w-12 py-3 pl-4"><span class="sr-only">Marcar</span></th>
                        <th class="px-4 py-3 font-medium">Artículo</th>
                        <th class="hidden px-3 py-3 font-medium lg:table-cell">Departamento</th>
                        <th class="px-3 py-3 text-right font-medium">Existencia</th>
                        <th class="hidden px-3 py-3 text-right font-medium lg:table-cell">Mín. / Máx.</th>
                        <th class="px-4 py-3 text-right font-medium">Estado</th>
                        @if ($this->puedeEntrada)<th class="w-14 py-3 pr-4"><span class="sr-only">Meter</span></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-anil-50">
                    @foreach ($lista as $a)
                        @php
                            $existencia = (float) $a->existencia;
                            $estado = $existencia <= 0 ? ['peligro', 'Agotado'] : ($a->estaBajoMinimo() ? ['aviso', 'Bajo el mínimo'] : ['ok', 'Bien']);
                        @endphp
                        <tr wire:key="inv-{{ $a->id }}" @class(['hover:bg-anil-50/50', 'bg-rosa-50' => in_array((string) $a->id, $seleccion, true)])>
                            <td class="py-3 pl-4">
                                <input type="checkbox" wire:model.live="seleccion" value="{{ $a->id }}" aria-label="Marcar {{ $a->descripcion }}"
                                       class="size-5 rounded border-anil-300 text-rosa-600 focus:ring-rosa-500">
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('inventario.kardex', $a) }}" wire:navigate class="font-semibold hover:text-rosa-600">{{ $a->descripcion }}</a>
                                <span class="block font-mono text-xs text-anil-600">{{ $a->clave }}</span>
                            </td>
                            <td class="hidden px-3 py-3 text-anil-700 lg:table-cell">{{ $a->departamento->nombre }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-display text-base font-extrabold">
                                {{ \App\Support\Numero::cantidad($a->existencia) }}<span class="ml-1 text-xs font-medium text-anil-500">{{ $a->unidad->abreviatura }}</span>
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-3 text-right font-mono text-anil-700 lg:table-cell">
                                {{ $a->existencia_minima !== null ? \App\Support\Numero::cantidad($a->existencia_minima) : '—' }} /
                                {{ $a->existencia_maxima !== null ? \App\Support\Numero::cantidad($a->existencia_maxima) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right"><x-badge :tono="$estado[0]">{{ $estado[1] }}</x-badge></td>
                            @if ($this->puedeEntrada)
                                <td class="py-3 pr-4 text-right">
                                    <button type="button" wire:click="abrirEntrada({{ $a->id }})" class="grid size-10 place-items-center rounded-lg border border-anil-200 text-rosa-600 hover:bg-rosa-50" aria-label="Meter mercancía de {{ $a->descripcion }}" title="Meter mercancía">
                                        <x-phosphor-plus-bold class="size-5" />
                                    </button>
                                </td>
                            @endif
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
                    <li wire:key="invm-{{ $a->id }}" @class(['flex items-center', 'bg-rosa-50' => in_array((string) $a->id, $seleccion, true)])>
                        <label class="grid min-h-14 shrink-0 place-items-center self-stretch pl-4 pr-1">
                            <input type="checkbox" wire:model.live="seleccion" value="{{ $a->id }}" aria-label="Marcar {{ $a->descripcion }}"
                                   class="size-6 rounded border-anil-300 text-rosa-600 focus:ring-rosa-500">
                        </label>
                        <a href="{{ route('inventario.kardex', $a) }}" wire:navigate class="flex min-w-0 flex-1 items-center justify-between gap-3 py-3.5 pl-2 pr-4">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $a->descripcion }}</span>
                                <span class="mt-0.5 block font-mono text-xs text-anil-600">{{ $a->clave }}</span>
                                <x-badge :tono="$estado[0]" class="mt-1.5">{{ $estado[1] }}</x-badge>
                            </span>
                            <span class="shrink-0 text-right font-display text-xl font-extrabold">
                                {{ \App\Support\Numero::cantidad($a->existencia) }}
                                <span class="block text-xs font-medium text-anil-500">{{ $a->unidad->abreviatura }}</span>
                            </span>
                        </a>
                        @if ($this->puedeEntrada)
                            <button type="button" wire:click="abrirEntrada({{ $a->id }})" class="mr-3 grid size-11 shrink-0 place-items-center rounded-lg border border-anil-200 text-rosa-600 active:bg-rosa-50" aria-label="Meter mercancía de {{ $a->descripcion }}">
                                <x-phosphor-plus-bold class="size-5" />
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5">{{ $lista->links() }}</div>
    @endif

    @if ($seleccion !== [])
        <div class="sticky bottom-20 z-20 mt-5 lg:bottom-4"
             x-data="imprimirLista({ pagina: @js(route('inventario.existencias', absolute: false)), escpos: @js(route('inventario.existencias-escpos', absolute: false)) })">
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-rosa-300 bg-white px-4 py-3">
                <p class="mr-auto font-semibold text-anil-900">
                    {{ count($seleccion) }} {{ count($seleccion) === 1 ? 'artículo marcado' : 'artículos marcados' }}
                    <span x-show="mensaje" x-text="mensaje" :class="error ? 'text-red-700' : 'text-emerald-700'" class="block text-sm font-medium"></span>
                </p>
                <x-secondary-button type="button" wire:click="quitarSeleccion">Desmarcar</x-secondary-button>
                <x-primary-button type="button" x-on:click="imprimir($wire.seleccion.map(Number))" x-bind:disabled="imprimiendo">
                    <x-phosphor-printer-bold class="size-4" />
                    <span x-text="imprimiendo ? 'Imprimiendo…' : 'Imprimir existencias'"></span>
                </x-primary-button>
            </div>
        </div>
    @endif

    @if ($this->puedeEntrada)
        <x-modal nombre="entrada" titulo="Meter mercancía">
            @if ($this->articuloEntrada)
                <form wire:submit="guardarEntrada" class="space-y-4">
                    <div>
                        <p class="font-semibold">{{ $this->articuloEntrada->descripcion }}</p>
                        <p class="text-sm text-anil-700">Hay <strong class="font-mono">{{ \App\Support\Numero::cantidad($this->articuloEntrada->existencia) }}</strong> {{ $this->articuloEntrada->unidad->abreviatura }}</p>
                    </div>
                    <div>
                        <x-input-label for="entradaCantidad" value="¿Cuántos entran?" />
                        <x-text-input wire:model="entradaCantidad" id="entradaCantidad" type="text" inputmode="decimal" autocomplete="off" class="text-center font-mono text-2xl font-bold"
                                      x-init="$watch('abierto', (v) => v && setTimeout(() => $el.focus(), 80))" />
                        <x-input-error :messages="$errors->get('entradaCantidad')" class="mt-1.5" />
                    </div>
                    <x-primary-button class="w-full justify-center">Guardar</x-primary-button>
                </form>
            @endif
        </x-modal>
    @endif

    <x-escaner-camara />
</div>
