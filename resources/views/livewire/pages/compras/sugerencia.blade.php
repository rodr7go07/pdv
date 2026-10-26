<?php

use App\Services\SugerenciaCompra;
use App\Models\Departamento;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    public function mount(): void
    {
        $this->authorize('compras.ver');
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
        );
    }

    #[Computed]
    public function filas()
    {
        return $this->sugerencia->filas();
    }

    #[Computed]
    public function total(): string
    {
        return (string) $this->filas->reduce(
            fn (\Brick\Math\BigDecimal $c, array $f) => $c->plus($f['importe']),
            \Brick\Math\BigDecimal::zero(),
        )->toScale(2);
    }

    /** Abre una compra nueva con estos artículos y cantidades ya cargados. */
    public function crearCompra(): void
    {
        $this->authorize('compras.registrar');

        if ($this->filas->isEmpty()) {
            return;
        }

        // Con la hora: si no se llega a la compra, la sugerencia caduca y no precarga cantidades viejas otro día.
        session()->put('compra_sugerida', ['en' => now()->timestamp, 'partidas' => $this->filas->pluck('sugerido', 'id')->all()]);
        $this->redirectRoute('compras.crear', navigate: true);
    }
}; ?>

<div>
    <x-slot name="titulo">Sugerencia de compra</x-slot>

    @php
        $filas = $this->filas;
        $s = $this->sugerencia;
        $campo = 'block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none';
    @endphp

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <x-enlace-volver :href="route('compras.index')">Volver a compras</x-enlace-volver>
        <div class="flex flex-wrap items-center gap-3">
            @if ($filas->isNotEmpty())
                <a href="{{ route('compras.sugerencia.exportar', ['dias' => $s->diasVenta, 'cobertura' => $s->diasCobertura, 'departamento' => $s->departamentoId]) }}"
                   class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                    <x-phosphor-microsoft-excel-logo-bold class="size-4" /> Exportar a Excel
                </a>
                @can('compras.registrar')
                    <x-primary-button type="button" wire:click="crearCompra"><x-phosphor-truck-bold class="size-4" /> Crear compra</x-primary-button>
                @endcan
            @endif
        </div>
    </div>

    <p class="mb-5 text-sm text-anil-700">
        Artículos que conviene comprar para cubrir <strong>{{ $s->diasCobertura }} {{ $s->diasCobertura === 1 ? 'día' : 'días' }}</strong> de venta, según lo vendido
        en los últimos <strong>{{ $s->diasVenta }} días</strong> y sin quedar debajo del mínimo de cada artículo.
    </p>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
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
            <label for="sg_depto" class="mb-1 block text-xs font-semibold text-anil-700">Departamento</label>
            <x-select wire:model.live="departamento" id="sg_depto">
                <option value="">Todos</option>
                @foreach ($this->departamentos as $d)
                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                @endforeach
            </x-select>
        </div>
    </div>

    @if ($filas->isEmpty())
        <x-empty icono="check-circle" titulo="Por ahora no hace falta comprar" texto="Todos los artículos cubren los días elegidos y están arriba de su mínimo." />
    @else
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm font-semibold text-anil-800">{{ $filas->count() }} {{ $filas->count() === 1 ? 'artículo' : 'artículos' }} por comprar</p>
            <p class="text-sm text-anil-700">Costo estimado: <strong class="font-display text-lg font-extrabold text-tinta">${{ number_format((float) $this->total, 2) }}</strong></p>
        </div>

        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <div class="hidden grid-cols-[minmax(0,1fr)_6rem_6rem_6rem_7rem_7rem] gap-x-4 border-b border-anil-100 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-anil-600 md:grid">
                <span>Artículo</span><span class="text-right">Existencia</span><span class="text-right">Vendido</span><span class="text-right">Alcanza</span><span class="text-right">Comprar</span><span class="text-right">Costo est.</span>
            </div>
            <ul class="divide-y divide-anil-50">
                @foreach ($filas as $f)
                    @php
                        $tono = ['agotado' => 'peligro', 'bajo_minimo' => 'aviso', 'cobertura' => 'gris'][$f['estado']];
                        $etiqueta = ['agotado' => 'Agotado', 'bajo_minimo' => 'Bajo el mínimo', 'cobertura' => 'Cobertura baja'][$f['estado']];
                        $num = fn (string $v, int $max = 3) => \App\Support\Numero::cantidad(number_format((float) $v, $max));
                    @endphp
                    <li wire:key="sg-{{ $f['id'] }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-3 md:grid-cols-[minmax(0,1fr)_6rem_6rem_6rem_7rem_7rem] md:px-5">
                        <div class="min-w-0">
                            <p class="line-clamp-2 font-semibold">{{ $f['descripcion'] }}</p>
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-anil-600">
                                <span class="font-mono">{{ $f['clave'] }}</span>
                                <x-badge :tono="$tono">{{ $etiqueta }}</x-badge>
                                @if ($f['minimo'] !== null)<span>Mínimo {{ $num($f['minimo']) }}</span>@endif
                            </p>
                        </div>
                        <p class="text-right font-display text-lg font-extrabold md:order-5 md:text-base">
                            <span class="md:hidden text-xs font-semibold text-anil-600">Comprar </span>{{ $num($f['sugerido']) }} <span class="text-xs font-semibold text-anil-600">{{ $f['unidad'] }}</span>
                        </p>
                        <p class="col-span-2 grid grid-cols-3 gap-2 text-xs text-anil-700 md:contents">
                            <span class="md:order-2 md:text-right md:text-sm"><span class="md:hidden">Existencia </span>{{ $num($f['existencia']) }}</span>
                            <span class="md:order-3 md:text-right md:text-sm"><span class="md:hidden">Vendido </span>{{ $num($f['vendido']) }}</span>
                            <span class="md:order-4 md:text-right md:text-sm"><span class="md:hidden">Alcanza </span>{{ $f['dias_restantes'] === null ? '—' : $num($f['dias_restantes'], 1).' d' }}</span>
                        </p>
                        <p class="hidden text-right text-sm md:order-6 md:block">${{ number_format((float) $f['importe'], 2) }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
        <p class="mt-3 text-xs text-anil-600">El costo estimado usa el último costo de compra (o el costo del artículo si nunca se compró). «Alcanza» son los días que dura la existencia actual al ritmo de venta.</p>
    @endif
</div>
