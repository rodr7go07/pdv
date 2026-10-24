<?php

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Turno;
use App\Services\ReporteVentas;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/** Tablero del dueño o encargado: lo importante de hoy, pensado para verse bien en el teléfono. */
new class extends Component
{
    public function mount(): void
    {
        $this->authorize('reportes.ver');
    }

    #[Computed]
    public function hoy(): array
    {
        $d = CarbonImmutable::now();

        return (new ReporteVentas($d, $d))->resumen();
    }

    #[Computed]
    public function ayer(): array
    {
        $d = CarbonImmutable::now()->subDay();

        return (new ReporteVentas($d, $d))->resumen();
    }

    /** Últimos 7 días, con ceros en los días sin ventas. */
    #[Computed]
    public function semana(): array
    {
        $fin = CarbonImmutable::now();
        $ventas = (new ReporteVentas($fin->subDays(6), $fin))->porPeriodo('dia')->keyBy('periodo');

        return collect(range(6, 0))->map(function (int $atras) use ($fin, $ventas) {
            $dia = $fin->subDays($atras);

            return ['dia' => $dia, 'neto' => (float) ($ventas[$dia->toDateString()]['neto'] ?? 0)];
        })->all();
    }

    #[Computed]
    public function top()
    {
        $d = CarbonImmutable::now();

        return (new ReporteVentas($d, $d))->porArticulo(5, 'importe');
    }

    #[Computed]
    public function bajoMinimo()
    {
        return Articulo::query()->with('unidad:id,abreviatura')->activos()->bajoMinimo()->orderByRaw('existencia - existencia_minima')->limit(5)->get();
    }

    #[Computed]
    public function totalBajoMinimo(): int
    {
        return Articulo::query()->activos()->bajoMinimo()->count();
    }

    #[Computed]
    public function turnosAbiertos()
    {
        return Turno::query()->abiertos()->with(['caja', 'usuario:id,name'])->get()
            ->map(fn (Turno $t) => ['turno' => $t, 'resumen' => \App\Services\ResumenTurno::calcular($t)]);
    }

    #[Computed]
    public function porCobrar(): string
    {
        return (string) Cliente::query()->sum('saldo');
    }

    public function variacion(float $hoy, float $ayer): ?float
    {
        return $ayer > 0 ? round(($hoy - $ayer) / $ayer * 100) : null;
    }
}; ?>

<div wire:poll.60s class="space-y-6">
    @php
        $m = fn ($v) => '$'.number_format((float) $v, 2);
        $h = $this->hoy;
        $var = $this->variacion((float) $h['neto'], (float) $this->ayer['neto']);
        $semana = $this->semana;
        $max = max(collect($semana)->max('neto'), 0.01);
    @endphp

    <section aria-labelledby="hoy-titulo">
        <div class="mb-3 flex items-baseline justify-between">
            <h2 id="hoy-titulo" class="rotulo">Hoy</h2>
            <a href="{{ route('reportes') }}" wire:navigate class="text-sm font-semibold text-rosa-600 underline">Ver reportes</a>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="col-span-2 rounded-xl bg-anil-900 p-5 text-white lg:col-span-1">
                <p class="rotulo !text-anil-300">Vendido hoy</p>
                <p class="mt-1 font-display text-4xl font-extrabold tracking-tight [font-stretch:112%]">{{ $m($h['neto']) }}</p>
                <p class="mt-1 text-xs text-anil-300">
                    @if ($var === null) Ayer no hubo ventas @else {{ $var >= 0 ? '▲' : '▼' }} {{ abs($var) }}% contra ayer ({{ $m($this->ayer['neto']) }}) @endif
                </p>
            </div>
            <div class="rounded-xl border border-anil-100 bg-white p-4"><p class="rotulo">Ventas</p><p class="mt-1 font-display text-3xl font-extrabold">{{ number_format($h['ventas']) }}</p></div>
            <div class="rounded-xl border border-anil-100 bg-white p-4"><p class="rotulo">Ticket promedio</p><p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ $m($h['ticket_promedio']) }}</p></div>
            <div class="col-span-2 rounded-xl border border-anil-100 bg-white p-4 lg:col-span-1"><p class="rotulo">Utilidad bruta</p><p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ $m($h['utilidad']) }}</p><p class="text-xs text-anil-600">Margen {{ $h['margen'] === null ? '—' : $h['margen'].'%' }}</p></div>
        </div>
    </section>

    <section class="rounded-xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)]" aria-labelledby="semana-titulo">
        <h2 id="semana-titulo" class="rotulo">Últimos 7 días</h2>
        <div class="mt-4 flex h-32 items-end gap-2" role="img" aria-label="Ventas netas de los últimos 7 días">
            @foreach ($semana as $d)
                <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                    <span class="font-mono text-[0.625rem] text-anil-600">{{ $d['neto'] > 0 ? '$'.number_format($d['neto'], 0) : '' }}</span>
                    <span class="w-full rounded-t-md {{ $loop->last ? 'bg-rosa-500' : 'bg-anil-300' }}" style="height: {{ max(round($d['neto'] / $max * 88), $d['neto'] > 0 ? 4 : 1) }}px"></span>
                    <span class="text-[0.6875rem] font-semibold text-anil-700">{{ ucfirst($d['dia']->translatedFormat('D')) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-2">
        <x-card titulo="Lo más vendido hoy" :padding="false">
            @if ($this->top->isEmpty())
                <div class="p-5"><p class="text-sm text-anil-700">Todavía no hay ventas hoy.</p></div>
            @else
                <ol class="divide-y divide-anil-50">
                    @foreach ($this->top as $a)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-anil-100 font-mono text-xs font-bold text-anil-800">{{ $loop->iteration }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $a['nombre'] }}</span><span class="text-xs text-anil-600">{{ rtrim(rtrim($a['cantidad'], '0'), '.') }} {{ $a['unidad'] }}</span></span>
                            <span class="font-mono font-semibold">{{ $m($a['neto']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        <x-card :titulo="'Por reabastecer'" :descripcion="$this->totalBajoMinimo > 0 ? $this->totalBajoMinimo.' '.($this->totalBajoMinimo === 1 ? 'artículo está' : 'artículos están').' en o debajo del mínimo' : null" :padding="false">
            @if ($this->bajoMinimo->isEmpty())
                <div class="p-5"><p class="flex items-center gap-2 text-sm text-emerald-800"><x-phosphor-check-circle-bold class="size-5" /> Todo el inventario está arriba del mínimo.</p></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($this->bajoMinimo as $a)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $a->descripcion }}</span><span class="text-xs text-anil-600">Mínimo {{ rtrim(rtrim((string) $a->existencia_minima, '0'), '.') ?: '0' }} {{ $a->unidad->abreviatura }}</span></span>
                            <x-badge :tono="(float) $a->existencia <= 0 ? 'peligro' : 'aviso'">{{ rtrim(rtrim((string) $a->existencia, '0'), '.') ?: '0' }} {{ $a->unidad->abreviatura }}</x-badge>
                        </li>
                    @endforeach
                </ul>
                @can('inventario.ver')
                    <div class="border-t border-anil-50 px-5 py-3"><a href="{{ route('inventario.index', ['filtro' => 'bajo']) }}" wire:navigate class="text-sm font-semibold text-rosa-600 underline">Ver todos</a></div>
                @endcan
            @endif
        </x-card>
    </div>

    <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-2">
        <x-card titulo="Cajas abiertas" :padding="false">
            @if ($this->turnosAbiertos->isEmpty())
                <div class="p-5"><p class="text-sm text-anil-700">No hay ningún turno abierto.</p></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($this->turnosAbiertos as $fila)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $fila['turno']->caja->nombre }} · {{ $fila['turno']->usuario->name }}</span><span class="text-xs text-anil-600">Desde las {{ $fila['turno']->abierto_at->format('H:i') }} · {{ $fila['resumen']['ventas']['cantidad'] }} ventas</span></span>
                            <span class="font-mono font-semibold">{{ $m($fila['resumen']['ventas']['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @can('clientes.ver')
            <x-card titulo="Cuentas por cobrar" :padding="true">
                <p class="font-display text-3xl font-extrabold">{{ $m($this->porCobrar) }}</p>
                <p class="mt-1 text-sm text-anil-700">Lo que los clientes todavía deben.</p>
                <a href="{{ route('clientes.index', ['filtro' => 'saldo']) }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-rosa-600 underline">Ver quién debe</a>
            </x-card>
        @endcan
    </div>
</div>
