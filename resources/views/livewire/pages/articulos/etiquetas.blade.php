<?php

use App\Models\Articulo;
use App\Models\Configuracion;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** @var list<array{id: int, clave: string, descripcion: string, precio: string, copias: int}> */
    public array $items = [];

    /** rollo = una etiqueta de 50×30 mm por página; hoja = cuadrícula de 3 columnas en tamaño carta */
    public string $formato = 'rollo';

    public bool $mostrarTienda = true;

    public function mount(): void
    {
        $this->authorize('articulos.ver');
    }

    #[On('articulo-elegido')]
    public function agregar(int $id): void
    {
        $articulo = Articulo::query()->find($id);

        if (! $articulo) {
            return;
        }

        foreach ($this->items as $i => $item) {
            if ($item['id'] === $id) {
                $this->items[$i]['copias'] = min($item['copias'] + 1, 200);
                $this->dispatch('enfocar-selector');

                return;
            }
        }

        $this->items[] = [
            'id' => $articulo->id,
            'clave' => $articulo->clave,
            'descripcion' => $articulo->descripcion,
            'precio' => number_format((float) $articulo->precio_1, 2, '.', ''),
            'copias' => 1,
        ];

        $this->dispatch('enfocar-selector');
    }

    public function quitar(int $indice): void
    {
        unset($this->items[$indice]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $i => $item) {
            $this->items[$i]['copias'] = max(1, min((int) ($item['copias'] ?? 1), 200));
        }
    }

    public function updatedFormato(): void
    {
        if (! in_array($this->formato, ['rollo', 'hoja'], true)) {
            $this->formato = 'rollo';
        }
    }

    /** Lista plana de etiquetas a imprimir (cada artículo repetido según sus copias, máximo 500). */
    public function etiquetas(): array
    {
        $lista = [];

        foreach ($this->items as $item) {
            for ($n = 0; $n < (int) $item['copias'] && count($lista) < 500; $n++) {
                $lista[] = $item;
            }
        }

        return $lista;
    }
}; ?>

<div>
    <x-slot name="titulo">Etiquetas de precio</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('articulos.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver a artículos
        </a>
    </x-slot>

    @php $etiquetas = $this->etiquetas(); $tienda = Configuracion::actual()->nombre_tienda; @endphp

    <style>
        .hoja-etiquetas { display: none; }
        @media print {
            body * { visibility: hidden !important; }
            .hoja-etiquetas, .hoja-etiquetas * { visibility: visible !important; }
            .hoja-etiquetas { display: block; position: absolute; left: 0; top: 0; width: 100%; }
            @if ($formato === 'rollo')
                @page { size: 50mm 30mm; margin: 0; }
                .et { width: 50mm; height: 30mm; page-break-after: always; }
            @else
                @page { size: letter; margin: 8mm; }
                .hoja-etiquetas { display: grid !important; grid-template-columns: repeat(3, 1fr); gap: 2mm; }
                .et { height: 38mm; border: 0.2mm dashed #999; }
            @endif
        }
        .et { box-sizing: border-box; padding: 1.5mm 2mm; font-family: Arial, Helvetica, sans-serif; color: #000; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
        .et .tienda { font-size: 6pt; letter-spacing: .05em; text-transform: uppercase; }
        .et .desc { font-size: 8pt; font-weight: 700; line-height: 1.1; max-height: 2.3em; overflow: hidden; }
        .et .precio { font-size: 17pt; font-weight: 800; line-height: 1; }
        .et svg { width: 100%; height: 8mm; }
        .et .clave { font-family: 'Courier New', monospace; font-size: 6.5pt; text-align: center; }
    </style>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <x-card titulo="Elige los artículos" descripcion="Escanea o busca cada artículo. Si lo agregas otra vez, suma una etiqueta.">
                <livewire:articulos.selector origen="etiquetas" placeholder="Escanea o busca" />

                @if ($items === [])
                    <p class="mt-5 rounded-lg border-2 border-dashed border-anil-200 px-4 py-8 text-center text-sm text-anil-700">Todavía no hay artículos en la lista.</p>
                @else
                    <ul class="mt-5 divide-y divide-anil-50">
                        @foreach ($items as $i => $item)
                            <li wire:key="et-{{ $item['id'] }}" class="flex items-center gap-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ $item['descripcion'] }}</p>
                                    <p class="font-mono text-xs text-anil-600">{{ $item['clave'] }} · ${{ $item['precio'] }}</p>
                                </div>
                                <label class="sr-only" for="copias-{{ $i }}">Copias</label>
                                <x-text-input wire:model.blur="items.{{ $i }}.copias" id="copias-{{ $i }}" type="number" min="1" max="200" class="!w-20 text-center font-mono" />
                                <button type="button" wire:click="quitar({{ $i }})" class="grid size-10 place-items-center rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar {{ $item['descripcion'] }}"><x-phosphor-x-bold class="size-4" /></button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6 lg:sticky lg:top-8">
            <x-card titulo="Formato">
                <div class="space-y-3">
                    <x-select wire:model.live="formato" aria-label="Formato de impresión">
                        <option value="rollo">Rollo de etiquetas 50 × 30 mm</option>
                        <option value="hoja">Hoja tamaño carta (3 columnas)</option>
                    </x-select>
                    <x-checkbox wire:model.live="mostrarTienda" label="Mostrar el nombre de la tienda" />
                </div>
                <p class="mt-4 text-sm text-anil-700"><strong>{{ count($etiquetas) }}</strong> {{ count($etiquetas) === 1 ? 'etiqueta' : 'etiquetas' }} para imprimir{{ count($etiquetas) >= 500 ? ' (máximo 500)' : '' }}.</p>
                <button type="button" onclick="window.print()" @disabled($etiquetas === [])
                        class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-rosa-500 px-5 font-display text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none disabled:pointer-events-none disabled:opacity-40">
                    <x-phosphor-printer-bold class="size-5" /> Imprimir etiquetas
                </button>
                <p class="mt-3 text-xs text-anil-600">En la ventana de impresión elige tu impresora de etiquetas y desactiva los márgenes y encabezados.</p>
            </x-card>
        </div>
    </div>

    {{-- Hoja que se imprime (oculta en pantalla) --}}
    <div class="hoja-etiquetas" aria-hidden="true">
        @foreach ($etiquetas as $n => $e)
            <div class="et" wire:key="hoja-{{ $n }}-{{ $e['id'] }}">
                @if ($mostrarTienda)<div class="tienda">{{ $tienda }}</div>@endif
                <div class="desc">{{ $e['descripcion'] }}</div>
                <div class="precio">${{ $e['precio'] }}</div>
                <div x-data="etiqueta(@js($e['clave']))"><svg x-init="dibujar($el)"></svg></div>
                <div class="clave">{{ $e['clave'] }}</div>
            </div>
        @endforeach
    </div>
</div>
