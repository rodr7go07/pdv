<?php

use App\Actions\Inventario\RegistrarEntradaRapida;
use App\Models\Articulo;
use App\Models\Proveedor;
use App\Support\Numero;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Entrada rápida de mercancía (decisión 84): se escanea lo que llegó (cada lectura suma 1), se ajustan cantidades
 * y se guarda todo de un jalón. Proveedor y nota son opcionales; el costo es el vigente salvo que se cambie.
 */
new #[Layout('layouts.app')] class extends Component
{
    /** @var list<array{articulo_id: int, clave: string, descripcion: string, unidad: string, existencia: string, cantidad: string, costo: string}> */
    public array $lineas = [];

    public string $proveedor_id = '';

    public string $notas = '';

    public ?string $exito = null;

    public function mount(): void
    {
        $this->authorize('compras.registrar');
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::query()->activos()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function verCostos(): bool
    {
        return auth()->user()->can('articulos.editar_precio');
    }

    #[On('articulo-elegido')]
    public function agregar(int $id): void
    {
        $this->exito = null;
        $articulo = Articulo::query()->with('unidad:id,abreviatura')->activos()->where('inventariable', true)->find($id);

        if (! $articulo) {
            return;
        }

        foreach ($this->lineas as $i => $linea) {
            if ($linea['articulo_id'] === $id) {
                $actual = is_numeric($linea['cantidad']) ? $linea['cantidad'] : '0';
                $this->lineas[$i]['cantidad'] = Numero::cantidad((string) BigDecimal::of($actual)->plus(1));
                // El que se acaba de escanear sube al principio para verlo sin buscar.
                $this->lineas = array_values([$i => $this->lineas[$i]] + $this->lineas);
                $this->dispatch('enfocar-selector');

                return;
            }
        }

        array_unshift($this->lineas, [
            'articulo_id' => $articulo->id,
            'clave' => $articulo->clave,
            'descripcion' => $articulo->descripcion,
            'unidad' => $articulo->unidad->abreviatura,
            'existencia' => (string) $articulo->existencia,
            'cantidad' => '1',
            'costo' => Numero::cantidad($articulo->costo) ?: '0',
        ]);
        $this->dispatch('enfocar-selector');
    }

    public function sumar(int $i, int $delta): void
    {
        if (! isset($this->lineas[$i])) {
            return;
        }
        $actual = is_numeric($this->lineas[$i]['cantidad']) ? BigDecimal::of($this->lineas[$i]['cantidad']) : BigDecimal::zero();
        $nueva = $actual->plus($delta);
        $this->lineas[$i]['cantidad'] = $nueva->isPositive() ? Numero::cantidad((string) $nueva) : '1';
    }

    public function quitar(int $i): void
    {
        unset($this->lineas[$i]);
        $this->lineas = array_values($this->lineas);
    }

    public function guardar(RegistrarEntradaRapida $registrar): void
    {
        $this->authorize('compras.registrar');
        $this->exito = null;

        if ($this->lineas === []) {
            $this->addError('lineas', 'Escanea o busca al menos un artículo.');

            return;
        }

        try {
            $compra = $registrar(
                array_map(fn ($l) => ['articulo_id' => $l['articulo_id'], 'cantidad' => (string) $l['cantidad'], 'costo' => $l['costo']], $this->lineas),
                auth()->user(),
                $this->proveedor_id !== '' ? (int) $this->proveedor_id : null,
                $this->notas,
            );
        } catch (ValidationException $e) {
            $errores = collect($e->errors())->mapWithKeys(fn ($m, $k) => [str_starts_with($k, 'partidas.') ? 'lineas.'.substr($k, 9) : (str_starts_with($k, 'partidas') ? 'lineas' : $k) => $m]);
            $this->setErrorBag($errores->all());

            return;
        }

        $piezas = count($this->lineas);
        $this->reset('lineas', 'notas');
        $this->exito = "Listo: entraron {$piezas} ".($piezas === 1 ? 'artículo' : 'artículos')." (folio {$compra->folio}). Las existencias ya se actualizaron.";
        $this->dispatch('enfocar-selector');
    }
}; ?>

<div>
    <x-slot name="titulo">Entrada de mercancía</x-slot>
    <x-slot name="acciones">
        <x-enlace-volver :href="route('inventario.index')">Volver al inventario</x-enlace-volver>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card titulo="¿Qué llegó?" descripcion="Escanea cada producto: cada lectura suma 1. Cambia la cantidad si llegó una caja completa.">
            @if ($exito)
                <x-alerta class="mb-4">{{ $exito }}</x-alerta>
            @endif

            <livewire:articulos.selector origen="inventario" :solo-inventariables="true" placeholder="Escanea o busca el producto" />
            <x-input-error :messages="$errors->get('lineas')" class="mt-2" />

            @if ($lineas === [])
                <p class="mt-6 rounded-lg border border-dashed border-anil-200 px-4 py-8 text-center text-anil-600">Todavía no hay productos. Escanea el primero.</p>
            @else
                <ul class="mt-4 divide-y divide-anil-100">
                    @foreach ($lineas as $i => $l)
                        @php
                            $cantidadValida = is_numeric($l['cantidad']) && (float) $l['cantidad'] > 0;
                            $quedara = $cantidadValida ? Numero::cantidad((string) BigDecimal::of($l['existencia'])->plus($l['cantidad'])) : '—';
                        @endphp
                        <li wire:key="en-{{ $l['articulo_id'] }}" class="py-3">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $l['descripcion'] }}</p>
                                    <p class="font-mono text-xs text-anil-600">{{ $l['clave'] }}</p>
                                    <p class="mt-1 text-sm text-anil-700">
                                        Había <strong class="font-mono">{{ Numero::cantidad($l['existencia']) }}</strong>
                                        → quedará <strong class="font-mono text-emerald-700">{{ $quedara }}</strong> {{ $l['unidad'] }}
                                    </p>
                                </div>
                                <button type="button" wire:click="quitar({{ $i }})" class="grid size-10 shrink-0 place-items-center rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar {{ $l['descripcion'] }}">
                                    <x-phosphor-trash-bold class="size-4" />
                                </button>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="sumar({{ $i }}, -1)" class="grid size-11 place-items-center rounded-lg border border-anil-200 text-lg font-bold hover:bg-anil-50" aria-label="Uno menos">−</button>
                                <input wire:model.blur="lineas.{{ $i }}.cantidad" type="text" inputmode="decimal" aria-label="Cantidad que entra"
                                       class="h-11 w-24 rounded-lg border border-anil-200 bg-white text-center font-mono text-lg font-bold text-tinta focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                                <button type="button" wire:click="sumar({{ $i }}, 1)" class="grid size-11 place-items-center rounded-lg border border-anil-200 text-lg font-bold hover:bg-anil-50" aria-label="Uno más">+</button>
                                <span class="text-sm text-anil-600">{{ $l['unidad'] }}</span>
                                @if ($this->verCostos)
                                    <label class="ml-auto flex items-center gap-2 text-sm text-anil-700">
                                        Costo $
                                        <input wire:model.blur="lineas.{{ $i }}.costo" type="text" inputmode="decimal" aria-label="Costo unitario"
                                               class="h-10 w-24 rounded-lg border border-anil-200 bg-white px-2 text-right font-mono text-tinta focus:border-anil-500 focus:outline-none">
                                    </label>
                                @endif
                            </div>
                            <x-input-error :messages="array_merge($errors->get('lineas.'.$i.'.cantidad'), $errors->get('lineas.'.$i.'.costo_unitario'))" class="mt-1.5" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card titulo="Guardar" class="lg:sticky lg:top-6">
            <div class="space-y-4">
                <p class="text-sm text-anil-700">
                    <strong class="font-display text-2xl font-extrabold text-tinta">{{ count($lineas) }}</strong>
                    {{ count($lineas) === 1 ? 'producto' : 'productos' }}
                </p>
                <div>
                    <x-input-label for="proveedor_id" value="Proveedor (opcional)" />
                    <x-select wire:model="proveedor_id" id="proveedor_id">
                        <option value="">Sin proveedor</option>
                        @foreach ($this->proveedores as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="notas" value="Nota (opcional)" />
                    <x-text-input wire:model="notas" id="notas" type="text" maxlength="200" placeholder="Ej. surtido del lunes" autocomplete="off" />
                </div>
                <x-primary-button type="button" wire:click="guardar" wire:loading.attr="disabled" class="w-full justify-center" :disabled="$lineas === []">
                    <x-phosphor-check-bold class="size-4" /> Guardar entrada
                </x-primary-button>
                @if ($this->verCostos)
                    <p class="text-xs text-anil-600">El costo viene con el vigente de cada producto; cámbialo solo si llegó a otro precio. Queda en Compras como «Entrada rápida».</p>
                @else
                    <p class="text-xs text-anil-600">Queda en Compras como «Entrada rápida» con el costo de cada producto.</p>
                @endif
            </div>
        </x-card>
    </div>

    <x-escaner-camara />
</div>
