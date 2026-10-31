<?php

use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Services\SugerenciaCompra;
use Brick\Math\BigDecimal;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(except: '30')]
    public string $dias = '30';

    #[Url(except: '15')]
    public string $cobertura = '15';

    #[Url(except: '')]
    public string $departamento = '';

    /** '' = todos | 'sin' = artículos sin proveedor principal | id de un proveedor */
    #[Url(except: '')]
    public string $proveedor = '';

    /** Lo que la persona escribe en cada renglón: [articulo_id => cantidad a comprar]. */
    public array $cantidades = [];

    /** @var list<int> renglones sugeridos que la persona quitó de la compra */
    public array $quitados = [];

    /** @var list<int> artículos que la persona agregó y el cálculo no sugirió */
    public array $agregados = [];

    public function mount(): void
    {
        $this->authorize('compras.ver');
    }

    /** Al cambiar el cálculo cambian los renglones: lo editado a mano ya no aplica. */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['dias', 'cobertura', 'departamento', 'proveedor'], true)) {
            $this->restablecer();
        }
    }

    /** Un proveedor con días de visita propone cubrir hasta su siguiente visita (se puede cambiar después). */
    public function updatedProveedor(): void
    {
        if ($dias = $this->diasHastaVisita) {
            $this->cobertura = (string) $dias;
        }
    }

    public function usarCobertura(int $dias): void
    {
        $this->cobertura = (string) max(1, min(SugerenciaCompra::COBERTURA_MAXIMA, $dias));
        $this->restablecer();
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::query()->activos()->whereHas('articulos')->orderBy('nombre')->get(['id', 'nombre', 'dias_visita']);
    }

    /** El proveedor elegido, o null si se ve todo o lo que no tiene proveedor. */
    #[Computed]
    public function proveedorElegido(): ?Proveedor
    {
        return ctype_digit($this->proveedor) ? Proveedor::query()->find((int) $this->proveedor) : null;
    }

    /** Días hasta la visita siguiente a la próxima del proveedor elegido (null si no tiene días de visita). */
    #[Computed]
    public function diasHastaVisita(): ?int
    {
        $dias = $this->proveedorElegido?->diasHastaSiguienteVisita();

        return $dias === null ? null : min($dias, SugerenciaCompra::COBERTURA_MAXIMA);
    }

    #[Computed]
    public function departamentos()
    {
        return Departamento::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function sugerencia(): SugerenciaCompra
    {
        return new SugerenciaCompra(
            (int) $this->dias,
            ctype_digit(trim($this->cobertura)) ? (int) $this->cobertura : 15,
            $this->departamento !== '' ? (int) $this->departamento : null,
            ctype_digit($this->proveedor) ? (int) $this->proveedor : ($this->proveedor === SugerenciaCompra::SIN_PROVEEDOR ? SugerenciaCompra::SIN_PROVEEDOR : null),
        );
    }

    /** Lo que sale del cálculo, sin los cambios de la persona. */
    #[Computed]
    public function calculadas()
    {
        return $this->sugerencia->filas()->keyBy('id');
    }

    /**
     * Lo que se va a comprar: lo calculado con las cantidades editadas, sin lo quitado y con lo agregado.
     * Cada renglón trae `editado`, `manual` y, si la cantidad escrita no sirve, `error`.
     */
    #[Computed]
    public function filas()
    {
        $filas = $this->calculadas->reject(fn (array $f, int $id) => in_array($id, $this->quitados, true))->values();

        $extras = collect($this->agregados)
            ->reject(fn (int $id) => $this->calculadas->has($id))
            ->map(fn (int $id) => Articulo::query()->with(['unidad:id,abreviatura,permite_decimales', 'proveedor:id,nombre'])->activos()->where('inventariable', true)->find($id))
            ->filter()
            ->map(fn (Articulo $a) => $this->sugerencia->filaManual($a));

        return $filas->concat($extras)->map(function (array $f) {
            $escrita = trim((string) ($this->cantidades[$f['id']] ?? $f['sugerido']));
            $valida = $this->cantidadValida($escrita, $f['decimales']);

            $f['manual'] = $f['estado'] === 'manual';
            $f['escrita'] = $escrita;

            if ($valida === null) {
                $f['error'] = $f['decimales']
                    ? 'Escribe un número mayor que cero (hasta 3 decimales).'
                    : 'Escribe un número entero mayor que cero.';
                $f['editado'] = true;

                return $f;
            }

            $f['error'] = null;
            $f['editado'] = ! $f['manual'] && ! BigDecimal::of($valida)->isEqualTo($f['sugerido']);

            return $this->sugerencia->conCantidad($f, $valida);
        })->values();
    }

    #[Computed]
    public function total(): string
    {
        return (string) $this->filas->reduce(
            fn (BigDecimal $c, array $f) => $f['error'] ? $c : $c->plus($f['importe']),
            BigDecimal::zero(),
        )->toScale(2);
    }

    #[Computed]
    public function hayCambios(): bool
    {
        return $this->quitados !== [] || $this->agregados !== [] || $this->filas->contains(fn (array $f) => $f['editado']);
    }

    /** Cantidad normalizada ("5", "2.5") o null si no es un número positivo válido para ese artículo. */
    private function cantidadValida(string $texto, bool $decimales): ?string
    {
        $patron = $decimales ? '/^\d{1,9}(\.\d{1,3})?$/' : '/^\d{1,9}$/';

        if (! preg_match($patron, $texto) || BigDecimal::of($texto)->isZero()) {
            return null;
        }

        return \App\Support\Numero::cantidad($texto);
    }

    public function fijarCantidad(int $id, string $valor): void
    {
        $this->authorize('compras.registrar');

        // Solo renglones que están en pantalla: nunca se guarda texto de un artículo cualquiera.
        if ($this->filas->contains(fn (array $f) => $f['id'] === $id)) {
            $this->cantidades[$id] = mb_substr(trim($valor), 0, 20);
        }

        unset($this->filas, $this->total, $this->hayCambios);
    }

    public function quitar(int $id): void
    {
        $this->authorize('compras.registrar');

        if (in_array($id, $this->agregados, true)) {
            $this->agregados = array_values(array_diff($this->agregados, [$id]));
        } elseif ($this->calculadas->has($id) && ! in_array($id, $this->quitados, true)) {
            $this->quitados[] = $id;
        }

        unset($this->cantidades[$id], $this->filas, $this->total, $this->hayCambios);
    }

    #[On('articulo-elegido')]
    public function agregar(int $id): void
    {
        $this->authorize('compras.registrar');

        if (in_array($id, $this->quitados, true)) {
            $this->quitados = array_values(array_diff($this->quitados, [$id])); // lo había quitado: vuelve con su cantidad sugerida
        } elseif (! $this->calculadas->has($id) && ! in_array($id, $this->agregados, true)) {
            $this->agregados[] = $id;
            $this->cantidades[$id] = '1';
        }

        unset($this->filas, $this->total, $this->hayCambios);
    }

    public function restablecer(): void
    {
        $this->reset('cantidades', 'quitados', 'agregados');
        unset($this->filas, $this->total, $this->hayCambios);
    }

    /** Exporta lo que se ve en pantalla (con los cambios), no solo lo calculado. */
    public function exportar(): void
    {
        if ($this->filas->isEmpty() || $this->filas->contains(fn (array $f) => $f['error'])) {
            return;
        }

        session()->put('sugerencia_editada', ['en' => now()->timestamp, 'dias' => $this->sugerencia->diasVenta, 'filas' => $this->filas->all()]);
        $this->redirectRoute('compras.sugerencia.exportar', ['editada' => 1]);
    }

    /** Abre una compra nueva con estos artículos y cantidades ya cargados. */
    public function crearCompra(): void
    {
        $this->authorize('compras.registrar');

        if ($this->filas->isEmpty()) {
            return;
        }

        if ($this->filas->contains(fn (array $f) => $f['error'])) {
            $this->addError('cantidades', 'Corrige las cantidades marcadas antes de crear la compra.');

            return;
        }

        // Con la hora: si no se llega a la compra, la sugerencia caduca y no precarga cantidades viejas otro día.
        session()->put('compra_sugerida', [
            'en' => now()->timestamp,
            'partidas' => $this->filas->pluck('sugerido', 'id')->all(),
            'proveedor_id' => $this->proveedorElegido?->id,
        ]);
        $this->redirectRoute('compras.crear', navigate: true);
    }
}; ?>

<div>
    <x-slot name="titulo">Sugerencia de compra</x-slot>

    @php
        $filas = $this->filas;
        $s = $this->sugerencia;
        $hayError = $filas->contains(fn (array $f) => $f['error']);
        $campo = 'block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none';
    @endphp

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <x-enlace-volver :href="route('compras.index')">Volver a compras</x-enlace-volver>
        <div class="flex flex-wrap items-center gap-3">
            @if ($filas->isNotEmpty())
                <button type="button" wire:click="exportar" @disabled($hayError)
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50 disabled:opacity-50">
                    <x-phosphor-microsoft-excel-logo-bold class="size-4" /> Exportar a Excel
                </button>
                @can('compras.registrar')
                    <x-primary-button type="button" wire:click="crearCompra"><x-phosphor-truck-bold class="size-4" /> Crear compra</x-primary-button>
                @endcan
            @endif
        </div>
    </div>

    <p class="mb-5 text-sm text-anil-700">
        Artículos que conviene comprar para cubrir <strong>{{ $s->diasCobertura }} {{ $s->diasCobertura === 1 ? 'día' : 'días' }}</strong> de venta, según lo vendido
        en los últimos <strong>{{ $s->diasVenta }} días</strong> y sin quedar debajo del mínimo de cada artículo.
        @can('compras.registrar')
            Puedes <strong>cambiar la cantidad</strong> de cada renglón, <strong>quitar</strong> los que no vas a comprar o <strong>agregar</strong> otros.
        @endcan
    </p>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="sg_dias" class="mb-1 block text-xs font-semibold text-anil-700">Vendido en los últimos</label>
            <x-select wire:model.live="dias" id="sg_dias">
                @foreach (\App\Services\SugerenciaCompra::DIAS_VENTA as $d)
                    <option value="{{ $d }}">{{ $d }} días</option>
                @endforeach
            </x-select>
        </div>
        <div>
            <label for="sg_cobertura" class="mb-1 block text-xs font-semibold text-anil-700">Días de cobertura a comprar</label>
            <input wire:model.live.debounce.500ms="cobertura" id="sg_cobertura" type="number" min="1" max="{{ \App\Services\SugerenciaCompra::COBERTURA_MAXIMA }}" step="1" inputmode="numeric" class="{{ $campo }}">
        </div>
        <div>
            <label for="sg_prov" class="mb-1 block text-xs font-semibold text-anil-700">Proveedor</label>
            <x-select wire:model.live="proveedor" id="sg_prov">
                <option value="">Todos</option>
                <option value="sin">Sin proveedor asignado</option>
                @foreach ($this->proveedores as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </x-select>
        </div>
        <div>
            <label for="sg_depto" class="mb-1 block text-xs font-semibold text-anil-700">Departamento</label>
            <x-select wire:model.live="departamento" id="sg_depto">
                <option value="">Todos</option>
                @foreach ($this->departamentos as $d)
                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                @endforeach
            </x-select>
        </div>
    </div>

    <div class="-mt-2 mb-5 flex flex-wrap items-center gap-2 text-sm text-anil-700">
        <span class="font-semibold">Cubrir:</span>
        <button type="button" wire:click="usarCobertura(7)" @class(['min-h-9 rounded-lg border px-3 font-semibold', 'border-rosa-500 bg-rosa-50 text-rosa-700' => $s->diasCobertura === 7, 'border-anil-200 bg-white text-anil-800 hover:bg-anil-50' => $s->diasCobertura !== 7])>Una semana (7 días)</button>
        @if ($this->diasHastaVisita)
            <button type="button" wire:click="usarCobertura({{ $this->diasHastaVisita }})" @class(['min-h-9 rounded-lg border px-3 font-semibold', 'border-rosa-500 bg-rosa-50 text-rosa-700' => $s->diasCobertura === $this->diasHastaVisita, 'border-anil-200 bg-white text-anil-800 hover:bg-anil-50' => $s->diasCobertura !== $this->diasHastaVisita])>
                Hasta su siguiente visita ({{ $this->diasHastaVisita }} {{ $this->diasHastaVisita === 1 ? 'día' : 'días' }})
            </button>
            <span class="text-xs text-anil-600">Pasa: {{ $this->proveedorElegido->visitaTexto() }}</span>
        @elseif ($this->proveedorElegido)
            <span class="text-xs text-anil-600">Este proveedor no tiene días de visita: se pueden poner en <strong>Compras → Proveedores</strong>.</span>
        @endif
    </div>

    @can('compras.registrar')
        <div class="mb-5 max-w-xl">
            <p class="mb-1 text-xs font-semibold text-anil-700">Agregar otro artículo a la compra</p>
            <livewire:articulos.selector origen="compras" placeholder="Escanea o busca" :solo-inventariables="true" />
        </div>
    @endcan

    @if ($filas->isEmpty())
        <x-empty icono="check-circle" titulo="Por ahora no hace falta comprar" texto="Todos los artículos cubren los días elegidos y están arriba de su mínimo." />
        @if ($this->hayCambios)
            <p class="mt-3 text-center"><button type="button" wire:click="restablecer" class="text-sm font-semibold text-rosa-600 underline">Volver a la sugerencia original</button></p>
        @endif
    @else
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm font-semibold text-anil-800">
                {{ $filas->count() }} {{ $filas->count() === 1 ? 'artículo' : 'artículos' }} por comprar
                @if ($this->hayCambios)
                    · <button type="button" wire:click="restablecer" class="font-semibold text-rosa-600 underline">Volver a la sugerencia original</button>
                @endif
            </p>
            <p class="text-sm text-anil-700">Costo estimado: <strong class="font-display text-lg font-extrabold text-tinta">${{ number_format((float) $this->total, 2) }}</strong></p>
        </div>
        @error('cantidades')<x-alerta tono="error" class="mb-3">{{ $message }}</x-alerta>@enderror

        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <div class="hidden grid-cols-[minmax(0,1fr)_5.5rem_5.5rem_5.5rem_8rem_6.5rem_2.75rem] gap-x-3 border-b border-anil-100 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-anil-600 md:grid">
                <span>Artículo</span><span class="text-right">Existencia</span><span class="text-right">Vendido</span><span class="text-right">Alcanza</span><span class="text-right">Comprar</span><span class="text-right">Costo est.</span><span></span>
            </div>
            <ul class="divide-y divide-anil-50">
                @foreach ($filas as $f)
                    @php
                        $tono = ['agotado' => 'peligro', 'bajo_minimo' => 'aviso', 'cobertura' => 'gris', 'manual' => 'rosa'][$f['estado']];
                        $etiqueta = ['agotado' => 'Agotado', 'bajo_minimo' => 'Bajo el mínimo', 'cobertura' => 'Cobertura baja', 'manual' => 'Agregado por ti'][$f['estado']];
                        $num = fn (string $v, int $max = 3) => \App\Support\Numero::cantidad(number_format((float) $v, $max));
                    @endphp
                    <li wire:key="sg-{{ $f['id'] }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 px-4 py-3 md:grid-cols-[minmax(0,1fr)_5.5rem_5.5rem_5.5rem_8rem_6.5rem_2.75rem] md:px-5">
                        <div class="min-w-0">
                            <p class="line-clamp-2 font-semibold">{{ $f['descripcion'] }}</p>
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-anil-600">
                                <span class="font-mono">{{ $f['clave'] }}</span>
                                <x-badge :tono="$tono">{{ $etiqueta }}</x-badge>
                                @if ($f['minimo'] !== null)<span>Mínimo {{ $num($f['minimo']) }}</span>@endif
                                @if ($proveedor === '')<span>{{ $f['proveedor'] ?? 'Sin proveedor' }}</span>@endif
                            </p>
                        </div>
                        <div class="md:order-5 md:text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @can('compras.registrar')
                                    <input type="text" inputmode="decimal" value="{{ $f['escrita'] }}" wire:change="fijarCantidad({{ $f['id'] }}, $event.target.value)"
                                           aria-label="Cantidad a comprar de {{ $f['descripcion'] }}" autocomplete="off"
                                           @class(['h-11 w-20 rounded-lg border bg-white px-2 text-right font-mono text-base font-bold focus:ring-2 focus:outline-none', 'border-anil-200 focus:border-rosa-500 focus:ring-rosa-500/20' => ! $f['error'], 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $f['error']])>
                                @else
                                    <span class="font-display text-lg font-extrabold md:text-base">{{ $num($f['sugerido']) }}</span>
                                @endcan
                                <span class="text-xs font-semibold text-anil-600">{{ $f['unidad'] }}</span>
                            </div>
                            @if ($f['editado'] && ! $f['error'])
                                <p class="mt-0.5 text-right text-xs text-anil-600">Sugerido {{ $num($this->calculadas[$f['id']]['sugerido'] ?? '0') }}</p>
                            @endif
                        </div>
                        <p class="col-span-2 grid grid-cols-3 gap-2 text-xs text-anil-700 md:contents">
                            <span class="md:order-2 md:text-right md:text-sm"><span class="md:hidden">Existencia </span>{{ $num($f['existencia']) }}</span>
                            <span class="md:order-3 md:text-right md:text-sm"><span class="md:hidden">Vendido </span>{{ $num($f['vendido']) }}</span>
                            <span class="md:order-4 md:text-right md:text-sm"><span class="md:hidden">Alcanza </span>{{ $f['dias_restantes'] === null ? '—' : $num($f['dias_restantes'], 1).' d' }}</span>
                        </p>
                        <p class="hidden text-right text-sm md:order-6 md:block">{{ $f['error'] ? '—' : '$'.number_format((float) $f['importe'], 2) }}</p>
                        @can('compras.registrar')
                            <button type="button" wire:click="quitar({{ $f['id'] }})" aria-label="Quitar {{ $f['descripcion'] }} de la compra" title="Quitar de la compra"
                                    class="hidden size-11 place-items-center rounded-lg text-anil-600 hover:bg-red-50 hover:text-red-700 md:order-7 md:grid">
                                <x-phosphor-trash-bold class="size-5" />
                            </button>
                            <button type="button" wire:click="quitar({{ $f['id'] }})" class="col-span-2 -mx-1 min-h-9 text-left text-xs font-semibold text-anil-700 underline md:hidden">Quitar de la compra</button>
                        @endcan
                        @if ($f['error'])
                            <p class="col-span-2 text-xs font-semibold text-red-700 md:order-8 md:col-span-7" role="alert">{{ $f['error'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <p class="mt-3 text-xs text-anil-600">El costo estimado usa el último costo de compra (o el costo del artículo si nunca se compró). «Alcanza» son los días que dura la existencia actual al ritmo de venta. Los cambios que hagas aquí se usan al crear la compra y al exportar; se pierden si cambias los filtros.</p>
    @endif
</div>
