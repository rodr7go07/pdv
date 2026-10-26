<?php

use App\Actions\Compras\RegistrarCompra;
use App\Models\Articulo;
use App\Models\Proveedor;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $proveedor_id = '';

    public string $factura = '';

    public string $fecha = '';

    public string $notas = '';

    /** @var list<array{articulo_id: int, clave: string, descripcion: string, unidad: string, cantidad: string, costo_unitario: string}> */
    public array $lineas = [];

    /** Aviso cuando la sugerencia traía más artículos de los que caben en una compra. */
    public string $avisoSugerencia = '';

    public function mount(): void
    {
        $this->authorize('compras.registrar');
        $this->fecha = now()->toDateString();

        $this->cargarSugerencia();
    }

    /** Partidas que llegan de «Sugerencia de compra» ({en: hora, partidas: [articulo_id => cantidad]}); caducan a los 10 minutos. */
    private function cargarSugerencia(): void
    {
        $sugerida = session()->pull('compra_sugerida');

        if (! is_array($sugerida) || now()->timestamp - (int) ($sugerida['en'] ?? 0) > 600 || ! is_array($sugerida['partidas'] ?? null)) {
            return;
        }

        // Una compra admite hasta RegistrarCompra::MAXIMO_PARTIDAS partidas; la sugerencia viene ordenada de lo más urgente a lo menos.
        $partidas = $sugerida['partidas'];
        $tope = RegistrarCompra::MAXIMO_PARTIDAS;
        $articulos = Articulo::query()->with('unidad:id,abreviatura')->whereIn('id', array_slice(array_keys($partidas), 0, $tope))->get()->keyBy('id');

        foreach (array_slice($partidas, 0, $tope, true) as $id => $cantidad) {
            if ($articulo = $articulos->get((int) $id)) {
                $this->lineas[] = $this->nuevaLinea($articulo, str_contains((string) $cantidad, '.') ? \App\Support\Numero::cantidad($cantidad) : (string) $cantidad);
            }
        }

        if (count($partidas) > $tope) {
            $this->avisoSugerencia = 'Se cargaron los primeros '.$tope.' de '.count($partidas).' artículos sugeridos (los más urgentes). Registra esta compra y vuelve a la sugerencia para el resto.';
        }
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::query()->activos()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[On('articulo-elegido')]
    public function agregar(int $id): void
    {
        $articulo = Articulo::query()->with('unidad:id,abreviatura')->find($id);

        if (! $articulo) {
            return;
        }

        foreach ($this->lineas as $i => $linea) {
            if ($linea['articulo_id'] === $id) {
                $this->lineas[$i]['cantidad'] = (string) BigDecimal::of($linea['cantidad'] !== '' && is_numeric($linea['cantidad']) ? $linea['cantidad'] : '0')->plus(1);
                $this->dispatch('enfocar-selector');

                return;
            }
        }

        $this->lineas[] = $this->nuevaLinea($articulo, '1');

        $this->dispatch('enfocar-selector');
    }

    /** @return array{articulo_id: int, clave: string, descripcion: string, unidad: string, cantidad: string, costo_unitario: string} */
    private function nuevaLinea(Articulo $articulo, string $cantidad): array
    {
        return [
            'articulo_id' => $articulo->id,
            'clave' => $articulo->clave,
            'descripcion' => $articulo->descripcion,
            'unidad' => $articulo->unidad->abreviatura,
            'cantidad' => $cantidad,
            'costo_unitario' => \App\Support\Numero::cantidad($articulo->ultimo_costo) ?: (\App\Support\Numero::cantidad($articulo->costo) ?: ''),
        ];
    }

    public function quitar(int $indice): void
    {
        unset($this->lineas[$indice]);
        $this->lineas = array_values($this->lineas);
    }

    /** Importe de un renglón (cantidad × costo), 0 si algún dato no es número. */
    public function importe(array $linea): string
    {
        if (! is_numeric($linea['cantidad']) || ! is_numeric($linea['costo_unitario'])) {
            return '0.00';
        }

        return (string) BigDecimal::of($linea['cantidad'])->multipliedBy($linea['costo_unitario'])->toScale(2, RoundingMode::HalfUp);
    }

    #[Computed]
    public function total(): string
    {
        return (string) collect($this->lineas)->reduce(
            fn (BigDecimal $carry, array $l) => $carry->plus($this->importe($l)),
            BigDecimal::zero(),
        )->toScale(2);
    }

    public function guardar(RegistrarCompra $registrar): void
    {
        $this->authorize('compras.registrar');

        try {
            $compra = $registrar(
                ['proveedor_id' => $this->proveedor_id, 'factura' => $this->factura, 'fecha' => $this->fecha, 'notas' => $this->notas],
                array_map(fn ($l) => ['articulo_id' => $l['articulo_id'], 'cantidad' => trim($l['cantidad']), 'costo_unitario' => trim($l['costo_unitario'])], $this->lineas),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $k) => [
                    str_starts_with($k, 'partidas.') ? 'lineas.'.substr($k, 9) : (in_array($k, ['proveedor_id', 'factura', 'fecha', 'notas']) ? $k : 'lineas') => $m,
                ])->all(),
            );
        }

        session()->flash('exito', "Compra {$compra->folio} registrada. Las existencias y los costos ya se actualizaron.");
        $this->redirectRoute('compras.ver', $compra, navigate: true);
    }
}; ?>

<div>
    <x-slot name="titulo">Nueva compra</x-slot>
    <x-slot name="acciones">
        <x-enlace-volver :href="route('compras.index')">Volver a compras</x-enlace-volver>
    </x-slot>

    <form wire:submit="guardar" class="space-y-6 pb-28 lg:pb-0">
        <x-card titulo="Datos de la compra">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-input-label for="proveedor_id" value="Proveedor" />
                    <x-select wire:model="proveedor_id" id="proveedor_id" required>
                        <option value="">Elige un proveedor…</option>
                        @foreach ($this->proveedores as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('proveedor_id')" class="mt-1.5" />
                    @if ($this->proveedores->isEmpty())
                        <p class="mt-1.5 text-sm text-amber-800">Primero da de alta un proveedor en la sección Proveedores.</p>
                    @endif
                </div>
                <div>
                    <x-input-label for="factura" value="Factura o remisión" />
                    <x-text-input wire:model="factura" id="factura" type="text" maxlength="60" autocomplete="off" />
                    <x-input-error :messages="$errors->get('factura')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="fecha" value="Fecha" />
                    <x-text-input wire:model="fecha" id="fecha" type="date" max="{{ now()->toDateString() }}" required />
                    <x-input-error :messages="$errors->get('fecha')" class="mt-1.5" />
                </div>
            </div>
        </x-card>

        <x-card titulo="Mercancía recibida" descripcion="Escanea cada artículo. Si lo escaneas otra vez, suma una pieza.">
            <livewire:articulos.selector origen="compras" placeholder="Escanea o busca" />

            @if ($avisoSugerencia !== '')
                <x-alerta tono="aviso" class="mt-3">{{ $avisoSugerencia }}</x-alerta>
            @endif

            <x-input-error :messages="$errors->get('lineas')" class="mt-3" />

            @if ($lineas === [])
                <p class="mt-5 rounded-lg border-2 border-dashed border-anil-200 px-4 py-8 text-center text-sm text-anil-700">Todavía no has agregado artículos.</p>
            @else
                <ul class="mt-5 divide-y divide-anil-50">
                    @foreach ($lineas as $i => $linea)
                        <li wire:key="linea-{{ $linea['articulo_id'] }}" class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_7rem_8rem_7rem_2.5rem] sm:items-center">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $linea['descripcion'] }}</p>
                                <p class="font-mono text-xs text-anil-600">{{ $linea['clave'] }}</p>
                            </div>
                            <div>
                                <label class="rotulo sm:sr-only" for="cant-{{ $i }}">Cantidad ({{ $linea['unidad'] }})</label>
                                <x-text-input wire:model.blur="lineas.{{ $i }}.cantidad" id="cant-{{ $i }}" type="text" inputmode="decimal" class="font-mono" />
                            </div>
                            <div>
                                <label class="rotulo sm:sr-only" for="costo-{{ $i }}">Costo sin impuestos</label>
                                <x-text-input wire:model.blur="lineas.{{ $i }}.costo_unitario" id="costo-{{ $i }}" type="text" inputmode="decimal" class="font-mono" placeholder="Costo" />
                            </div>
                            <p class="font-mono font-semibold sm:text-right">${{ number_format((float) $this->importe($linea), 2) }}</p>
                            <button type="button" wire:click="quitar({{ $i }})" class="grid size-10 place-items-center justify-self-end rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar {{ $linea['descripcion'] }}">
                                <x-phosphor-x-bold class="size-4" />
                            </button>
                            <x-input-error :messages="$errors->get('lineas.'.$i.'.*')" class="sm:col-span-5" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card titulo="Notas">
            <x-text-input wire:model="notas" type="text" maxlength="2000" placeholder="Opcional" aria-label="Notas" autocomplete="off" />
        </x-card>

        <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 flex items-center justify-between gap-3 border-t border-anil-100 bg-white/95 px-4 py-3 backdrop-blur lg:static lg:rounded-xl lg:border lg:px-5">
            <div>
                <p class="rotulo">Total (sin impuestos)</p>
                <p class="font-display text-2xl font-extrabold">${{ number_format((float) $this->total, 2) }}</p>
            </div>
            <x-primary-button wire:loading.attr="disabled" wire:target="guardar" :disabled="$lineas === []">Registrar compra</x-primary-button>
        </div>
    </form>
</div>
