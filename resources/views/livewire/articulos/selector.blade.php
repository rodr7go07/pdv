<?php

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Models\Articulo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/**
 * Campo para elegir un artículo escaneando su código o buscándolo por nombre.
 * Al elegir uno envía el evento "articulo-elegido" (id) al componente padre.
 */
new class extends Component
{
    public string $texto = '';

    public string $placeholder = 'Escanea un código o busca por nombre';

    /** Solo artículos que llevan inventario. */
    #[Locked]
    public bool $soloInventariables = false;

    public ?string $aviso = null;

    /** Pantalla donde está el campo (App\Enums\OrigenEscaneo): sirve para la bitácora de escaneos. */
    #[Locked]
    public string $origen = 'compras';

    public function mount(string $placeholder = '', bool $soloInventariables = false, string $origen = 'compras'): void
    {
        $this->origen = OrigenEscaneo::tryFrom($origen)?->value ?? 'compras';

        if ($placeholder !== '') {
            $this->placeholder = $placeholder;
        }

        $this->soloInventariables = $soloInventariables;
    }

    public function updatedTexto(): void
    {
        $this->aviso = null;
    }

    /** Enter (el escáner lo envía): si hay coincidencia exacta se elige; si no, el primer resultado único. */
    public function entrar(string $medio = 'lector'): void
    {
        $texto = trim($this->texto);

        if ($texto === '') {
            return;
        }

        app(RegistrarEscaneo::class)($texto, OrigenEscaneo::from($this->origen), MedioEscaneo::tryFrom($medio) ?? MedioEscaneo::Lector, auth()->user());

        $articulo = Articulo::porClave($texto);

        if (! $articulo) {
            $resultados = $this->resultados;
            $articulo = $resultados->count() === 1 ? $resultados->first() : null;
        }

        if (! $articulo) {
            $this->aviso = $this->resultados->isEmpty() ? "No hay ningún artículo con «{$texto}»." : null;

            return;
        }

        $this->elegir($articulo->id);
    }

    public function elegir(int $id): void
    {
        $articulo = Articulo::query()->activos()->find($id);

        if (! $articulo || ($this->soloInventariables && ! $articulo->inventariable)) {
            $this->aviso = $articulo ? 'Este artículo no lleva control de inventario.' : 'Ese artículo no está disponible.';

            return;
        }

        $this->reset('texto', 'aviso');
        $this->dispatch('articulo-elegido', id: $articulo->id);
    }

    #[Computed]
    public function resultados()
    {
        $texto = trim($this->texto);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        return Articulo::query()
            ->with('unidad:id,abreviatura')
            ->activos()
            ->when($this->soloInventariables, fn ($q) => $q->where('inventariable', true))
            ->buscar($texto)
            ->orderBy('descripcion')
            ->limit(8)
            ->get(['id', 'clave', 'descripcion', 'unidad_medida_id', 'precio_1', 'existencia']);
    }
}; ?>

<div class="relative" x-data x-on:enfocar-selector.window="$refs.entrada.focus()"
     x-on:codigo-escaneado.window="if ($event.detail.destino === 'selector') { $wire.texto = $event.detail.codigo; $wire.entrar('camara'); }">
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <x-phosphor-barcode-bold class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-anil-400" />
            <input x-ref="entrada" wire:model.live.debounce.250ms="texto" wire:keydown.enter.prevent="entrar" type="search"
                   placeholder="{{ $placeholder }}" aria-label="Buscar artículo" autocomplete="off" spellcheck="false" autofocus
                   class="block w-full rounded-lg border border-anil-200 bg-white py-3 pl-11 pr-3.5 text-base text-tinta placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        </div>
        <x-boton-camara destino="selector" />
    </div>

    @if ($aviso)
        <p class="mt-2 text-sm font-medium text-amber-800" role="alert">{{ $aviso }}</p>
    @endif

    @if ($this->resultados->isNotEmpty())
        <ul class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 divide-y divide-anil-50 overflow-y-auto rounded-xl border border-anil-100 bg-white shadow-xl">
            @foreach ($this->resultados as $a)
                <li wire:key="sel-{{ $a->id }}">
                    <button type="button" wire:click="elegir({{ $a->id }})" class="flex min-h-14 w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-anil-50">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-tinta">{{ $a->descripcion }}</span>
                            <span class="block font-mono text-xs text-anil-600">{{ $a->clave }}</span>
                        </span>
                        <span class="shrink-0 text-right text-xs text-anil-700">
                            <span class="block font-display text-sm font-extrabold text-tinta">${{ number_format((float) $a->precio_1, 2) }}</span>
                            Existencia {{ rtrim(rtrim((string) $a->existencia, '0'), '.') ?: '0' }} {{ $a->unidad->abreviatura }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <x-escaner-camara />
</div>
