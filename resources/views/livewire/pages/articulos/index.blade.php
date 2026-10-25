<?php

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
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

    #[Url(except: 'activos')]
    public string $estado = 'activos';

    public ?string $aviso = null;

    public function mount(): void
    {
        $this->authorize('articulos.ver');
    }

    public function updating(): void
    {
        $this->resetPage();
        $this->aviso = null;
    }

    /** El escáner envía el código y Enter: si coincide exacto, se abre ese artículo. */
    public function buscarClave(string $medio = 'lector'): void
    {
        app(RegistrarEscaneo::class)($this->buscar, OrigenEscaneo::Articulos, MedioEscaneo::tryFrom($medio) ?? MedioEscaneo::Lector, auth()->user());

        $articulo = Articulo::porClave($this->buscar);

        if (! $articulo) {
            $this->aviso = $this->buscar === '' ? null : 'No hay ningún artículo con la clave «'.$this->buscar.'».';

            return;
        }

        if (auth()->user()->can('articulos.editar')) {
            $this->redirectRoute('articulos.editar', $articulo, navigate: true);

            return;
        }

        $this->buscar = $articulo->clave;
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

    #[Computed]
    public function articulos()
    {
        return Articulo::query()
            ->with(['departamento:id,nombre', 'unidad:id,abreviatura', 'impuestos'])
            ->withCount('clavesAlternas')
            ->buscar($this->buscar)
            ->when($this->departamento !== '', fn ($q) => $q->where('departamento_id', $this->departamento))
            ->when($this->estado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($this->estado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy('descripcion')
            ->paginate(15);
    }
}; ?>

<div>
    <x-slot name="titulo">Artículos</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('articulos.etiquetas') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
            <x-phosphor-tag-bold class="size-4" /> Etiquetas
        </a>
        @can('articulos.importar')
            <a href="{{ route('articulos.importar') }}" wire:navigate
               class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                <x-phosphor-file-xls-bold class="size-4" /> Importar
            </a>
        @endcan
        @can('articulos.crear')
            <a href="{{ route('articulos.crear') }}" wire:navigate
               class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-rosa-500 px-4 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none">
                <x-phosphor-plus-bold class="size-4" /> Nuevo
            </a>
        @endcan
    </x-slot>

    @if (session('exito'))
        <x-alerta class="mb-5">{{ session('exito') }}</x-alerta>
    @endif

    {{-- Búsqueda y filtros --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem_10rem]">
        <div class="flex gap-2" x-data x-on:codigo-escaneado.window="if ($event.detail.destino === 'buscar-articulos') { $wire.buscar = $event.detail.codigo; $wire.buscarClave('camara'); }">
            <div class="relative min-w-0 flex-1">
                <x-phosphor-barcode-bold class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-anil-400" />
                <input wire:model.live.debounce.300ms="buscar" wire:keydown.enter="buscarClave" type="search" autofocus
                       placeholder="Escanea un código o busca por nombre o clave"
                       aria-label="Buscar artículos" autocomplete="off" spellcheck="false"
                       class="block w-full rounded-lg border border-anil-200 bg-white py-2.5 pl-11 pr-3.5 text-base text-tinta placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
            </div>
            <x-boton-camara destino="buscar-articulos" />
        </div>
        <x-select wire:model.live="departamento" aria-label="Departamento">
            <option value="">Todos los departamentos</option>
            @foreach ($this->departamentos as $d)
                <option value="{{ $d->id }}">{{ $d->nombre }}</option>
            @endforeach
        </x-select>
        <x-select wire:model.live="estado" aria-label="Estado">
            <option value="activos">Activos</option>
            <option value="inactivos">Inactivos</option>
            <option value="todos">Todos</option>
        </x-select>
    </div>

    @if ($aviso)
        <p class="mb-4 flex items-start gap-2 rounded-lg bg-etiqueta/25 px-4 py-3 text-sm font-medium text-amber-900" role="alert">
            <x-phosphor-warning-bold class="mt-0.5 size-5 shrink-0" /> {{ $aviso }}
        </p>
    @endif

    @php $lista = $this->articulos; $puedeEditar = auth()->user()->can('articulos.editar'); @endphp

    @if ($lista->isEmpty())
        <x-empty icono="package" titulo="No encontramos artículos"
                 :texto="$buscar !== '' || $departamento !== '' ? 'Prueba con otra palabra o quita los filtros.' : 'Aún no has dado de alta ningún artículo.'">
            @can('articulos.crear')
                @if ($buscar === '' && $departamento === '')
                    <a href="{{ route('articulos.crear') }}" wire:navigate class="rounded-lg bg-anil-900 px-4 py-2.5 text-sm font-semibold text-white">Crear el primero</a>
                @endif
            @endcan
        </x-empty>
    @else
        {{-- Tabla (PC y tablet) --}}
        <div class="hidden overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)] md:block">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                        <th class="px-4 py-3 font-medium">Clave</th>
                        <th class="px-3 py-3 font-medium">Descripción</th>
                        <th class="hidden px-3 py-3 font-medium lg:table-cell">Departamento</th>
                        @if ($this->verCostos)
                            <th class="hidden px-3 py-3 text-right font-medium xl:table-cell">Costo</th>
                            <th class="hidden px-3 py-3 text-right font-medium xl:table-cell">Margen</th>
                        @endif
                        <th class="px-3 py-3 text-right font-medium">Precio</th>
                        <th class="px-4 py-3 text-right font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-anil-50">
                    @foreach ($lista as $a)
                        <tr wire:key="art-{{ $a->id }}" @class(['hover:bg-anil-50/50', 'opacity-60' => ! $a->activo])>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-[0.8125rem] text-anil-800">{{ $a->clave }}</td>
                            <td class="px-3 py-3">
                                @if ($puedeEditar)
                                    <a href="{{ route('articulos.editar', $a) }}" wire:navigate class="font-semibold text-tinta hover:text-rosa-600">{{ $a->descripcion }}</a>
                                @else
                                    <span class="font-semibold">{{ $a->descripcion }}</span>
                                @endif
                                @if ($a->claves_alternas_count)
                                    <span class="ml-1 text-xs text-anil-500">+{{ $a->claves_alternas_count }} {{ $a->claves_alternas_count === 1 ? 'clave' : 'claves' }}</span>
                                @endif
                            </td>
                            <td class="hidden px-3 py-3 text-anil-700 lg:table-cell">{{ $a->departamento->nombre }}</td>
                            @if ($this->verCostos)
                                <td class="hidden px-3 py-3 text-right font-mono text-anil-700 xl:table-cell">${{ number_format((float) $a->costo, 2) }}</td>
                                <td class="hidden px-3 py-3 text-right font-mono text-anil-700 xl:table-cell">{{ $a->margen() === null ? '—' : $a->margen().'%' }}</td>
                            @endif
                            <td class="whitespace-nowrap px-3 py-3 text-right font-display text-base font-extrabold">${{ number_format((float) $a->precio_1, 2) }}<span class="ml-1 text-xs font-medium text-anil-500">/{{ $a->unidad->abreviatura }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <x-badge :tono="$a->activo ? 'ok' : 'gris'">{{ $a->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tarjetas (teléfono) --}}
        <ul class="space-y-3 md:hidden">
            @foreach ($lista as $a)
                <li wire:key="card-{{ $a->id }}">
                    @php $tag = $puedeEditar ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($puedeEditar) href="{{ route('articulos.editar', $a) }}" wire:navigate @endif
                        @class(['block rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]', 'opacity-60' => ! $a->activo])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold leading-snug text-tinta">{{ $a->descripcion }}</p>
                                <p class="mt-1 font-mono text-xs text-anil-700">{{ $a->clave }}</p>
                            </div>
                            <p class="shrink-0 font-display text-xl font-extrabold">${{ number_format((float) $a->precio_1, 2) }}</p>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <x-badge>{{ $a->departamento->nombre }}</x-badge>
                            <x-badge :tono="$a->activo ? 'ok' : 'gris'">{{ $a->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                            @if ($this->verCostos)
                                <span class="font-mono text-anil-600">Costo ${{ number_format((float) $a->costo, 2) }}</span>
                            @endif
                        </div>
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>

        <div class="mt-5">{{ $lista->links() }}</div>
    @endif

    <x-escaner-camara />
</div>
