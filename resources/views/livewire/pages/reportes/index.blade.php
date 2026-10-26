<?php

use App\Models\Caja;
use App\Models\User;
use App\Services\ReporteVentas;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'ver', except: 'resumen')]
    public string $seccion = 'resumen';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $caja = '';

    #[Url(except: '')]
    public string $cajero = '';

    #[Url(except: 'dia')]
    public string $agrupar = 'dia';

    #[Url(except: 'importe')]
    public string $orden = 'importe';

    public const SECCIONES = [
        'resumen' => 'Resumen',
        'periodo' => 'Por periodo',
        'departamento' => 'Por departamento',
        'articulo' => 'Por artículo',
        'cajero' => 'Por cajero',
        'pago' => 'Por forma de pago',
        'inventario' => 'Inventario',
    ];

    public function mount(): void
    {
        $this->authorize('reportes.ver');

        if ($this->desde === '') {
            $this->desde = CarbonImmutable::now()->startOfMonth()->toDateString();
        }

        if ($this->hasta === '') {
            $this->hasta = CarbonImmutable::now()->toDateString();
        }

        $this->normalizar();
    }

    public function updated(): void
    {
        $this->normalizar();
    }

    private function normalizar(): void
    {
        if (! array_key_exists($this->seccion, self::SECCIONES)) {
            $this->seccion = 'resumen';
        }

        if (! in_array($this->agrupar, ['dia', 'semana', 'mes'], true)) {
            $this->agrupar = 'dia';
        }

        if (! in_array($this->orden, ['importe', 'cantidad', 'utilidad'], true)) {
            $this->orden = 'importe';
        }

        if ($this->desde !== '' && $this->hasta !== '' && $this->hasta < $this->desde) {
            $this->hasta = $this->desde;
        }
    }

    public function rango(string $nombre): void
    {
        $hoy = CarbonImmutable::now();

        [$this->desde, $this->hasta] = match ($nombre) {
            'hoy' => [$hoy->toDateString(), $hoy->toDateString()],
            'ayer' => [$hoy->subDay()->toDateString(), $hoy->subDay()->toDateString()],
            'semana' => [$hoy->subDays(6)->toDateString(), $hoy->toDateString()],
            'mes' => [$hoy->startOfMonth()->toDateString(), $hoy->toDateString()],
            'mes_pasado' => [$hoy->subMonthNoOverflow()->startOfMonth()->toDateString(), $hoy->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$this->desde, $this->hasta],
        };
    }

    #[Computed]
    public function cajas()
    {
        return Caja::query()->withTrashed()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function cajeros()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function reporte(): ReporteVentas
    {
        return new ReporteVentas(
            $this->desde ?: CarbonImmutable::now()->startOfMonth(),
            $this->hasta ?: CarbonImmutable::now(),
            $this->caja !== '' ? (int) $this->caja : null,
            $this->cajero !== '' ? (int) $this->cajero : null,
        );
    }

    #[Computed]
    public function datos()
    {
        return match ($this->seccion) {
            'periodo' => $this->reporte->porPeriodo($this->agrupar),
            'departamento' => $this->reporte->porDepartamento(),
            'articulo' => $this->reporte->porArticulo(100, $this->orden),
            'cajero' => $this->reporte->porCajero(),
            'pago' => $this->reporte->porFormaPago(),
            default => null,
        };
    }

    public function urlExportar(): string
    {
        return route('reportes.exportar', ['seccion' => $this->seccion] + array_filter([
            'desde' => $this->desde, 'hasta' => $this->hasta, 'caja' => $this->caja, 'cajero' => $this->cajero,
            'agrupar' => $this->agrupar, 'orden' => $this->orden,
        ], fn ($v) => $v !== ''));
    }
}; ?>

<div>
    <x-slot name="titulo">Reportes</x-slot>
    {{-- Va dentro del componente (no en el encabezado del layout) para que siga la pestaña y las fechas elegidas --}}
    <div class="mb-4 flex justify-end">
        <a href="{{ $this->urlExportar() }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
            <x-phosphor-file-xls-bold class="size-4" /> Exportar a Excel
        </a>
    </div>

    @php
        $m = fn ($v) => '$'.number_format((float) $v, 2);
        $pct = fn ($v) => $v === null ? '—' : $v.'%';
        $conPeriodo = $seccion !== 'inventario';
    @endphp

    {{-- Secciones --}}
    <nav class="mb-5 flex gap-1 overflow-x-auto rounded-xl bg-anil-100/60 p-1" aria-label="Reportes">
        @foreach ($this::SECCIONES as $clave => $etiqueta)
            <button type="button" wire:click="$set('seccion', '{{ $clave }}')"
                    @class(['min-h-11 shrink-0 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition', 'bg-white text-anil-900 shadow-sm' => $seccion === $clave, 'text-anil-700 hover:text-anil-900' => $seccion !== $clave])
                    @if ($seccion === $clave) aria-current="page" @endif>{{ $etiqueta }}</button>
        @endforeach
    </nav>

    {{-- Filtros --}}
    @if ($conPeriodo)
        <div class="mb-6 space-y-3">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Rangos rápidos">
                @foreach (['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Últimos 7 días', 'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado'] as $k => $e)
                    <button type="button" wire:click="rango('{{ $k }}')" class="min-h-10 rounded-lg border border-anil-200 bg-white px-3.5 text-sm font-semibold text-anil-800 hover:bg-anil-50">{{ $e }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div><x-input-label for="r_desde" value="Desde" /><x-text-input wire:model.live="desde" id="r_desde" type="date" /></div>
                <div><x-input-label for="r_hasta" value="Hasta" /><x-text-input wire:model.live="hasta" id="r_hasta" type="date" /></div>
                <div><x-input-label for="r_caja" value="Caja" />
                    <x-select wire:model.live="caja" id="r_caja"><option value="">Todas</option>@foreach ($this->cajas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach</x-select></div>
                <div><x-input-label for="r_cajero" value="Cajero" />
                    <x-select wire:model.live="cajero" id="r_cajero"><option value="">Todos</option>@foreach ($this->cajeros as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</x-select></div>
            </div>
        </div>
    @endif

    {{-- Resumen --}}
    @if ($seccion === 'resumen')
        @php $r = $this->reporte->resumen(); @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-kpi etiqueta="Ventas" tamano="grande" :sombra="false">{{ number_format($r['ventas']) }}<x-slot:nota>{{ $r['canceladas'] }} canceladas</x-slot:nota></x-kpi>
            <x-kpi etiqueta="Venta neta" :sombra="false">{{ $m($r['neto']) }}<x-slot:nota>Vendido {{ $m($r['bruto']) }} − devoluciones {{ $m($r['devuelto']) }}</x-slot:nota></x-kpi>
            <x-kpi etiqueta="Ticket promedio" :sombra="false">{{ $m($r['ticket_promedio']) }}</x-kpi>
            <x-kpi etiqueta="Utilidad bruta" tono="oscuro" :sombra="false">{{ $m($r['utilidad']) }}<x-slot:nota>Margen {{ $pct($r['margen']) }}</x-slot:nota></x-kpi>
        </div>
        <x-card titulo="Detalle" class="mt-6">
            <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt class="text-anil-700">Costo de lo vendido</dt><dd class="font-mono font-semibold">{{ $m($r['costo']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-anil-700">Descuentos dados</dt><dd class="font-mono font-semibold">{{ $m($r['descuento']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-anil-700">Impuestos cobrados</dt><dd class="font-mono font-semibold">{{ $m($r['impuestos']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-anil-700">Devoluciones</dt><dd class="font-mono font-semibold">{{ $m($r['devuelto']) }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-anil-600">La utilidad bruta es el precio de venta sin impuestos menos el costo que tenía cada artículo al venderse. Las devoluciones se restan en el periodo de la venta original.</p>
        </x-card>
    @endif

    {{-- Tablas por sección --}}
    @if (in_array($seccion, ['periodo', 'departamento', 'articulo', 'cajero'], true))
        @if ($seccion === 'periodo')
            <div class="mb-4 max-w-xs"><x-input-label for="r_agrupar" value="Agrupar por" />
                <x-select wire:model.live="agrupar" id="r_agrupar"><option value="dia">Día</option><option value="semana">Semana</option><option value="mes">Mes</option></x-select></div>
        @elseif ($seccion === 'articulo')
            <div class="mb-4 max-w-xs"><x-input-label for="r_orden" value="Ordenar por" />
                <x-select wire:model.live="orden" id="r_orden"><option value="importe">Importe vendido</option><option value="cantidad">Piezas vendidas</option><option value="utilidad">Utilidad</option></x-select></div>
        @endif

        @php $filas = $this->datos; $maximo = max((float) ($filas->max(fn ($f) => (float) $f['neto']) ?? 0), 0.01); @endphp

        @if ($filas->isEmpty())
            <x-empty icono="chart-bar" titulo="Sin ventas en este periodo" texto="Cambia las fechas o los filtros para ver otras ventas." />
        @else
            <div class="overflow-x-auto rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead>
                        <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                            <th class="px-4 py-3 font-medium">{{ ['periodo' => 'Periodo', 'departamento' => 'Departamento', 'articulo' => 'Artículo', 'cajero' => 'Cajero'][$seccion] }}</th>
                            @if (in_array($seccion, ['periodo', 'cajero'], true))<th class="px-3 py-3 text-right font-medium">Ventas</th>@endif
                            @if ($seccion === 'articulo')<th class="px-3 py-3 text-right font-medium">Piezas</th>@endif
                            <th class="px-3 py-3 text-right font-medium">Neto</th>
                            <th class="px-3 py-3 text-right font-medium">Costo</th>
                            <th class="px-3 py-3 text-right font-medium">Utilidad</th>
                            <th class="px-4 py-3 text-right font-medium">Margen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-anil-50">
                        @foreach ($filas as $f)
                            <tr wire:key="rep-{{ $seccion }}-{{ $loop->index }}">
                                <td class="px-4 py-2.5">
                                    <span class="block font-semibold">{{ $f['etiqueta'] ?? $f['nombre'] }}</span>
                                    @if ($seccion === 'articulo')<span class="font-mono text-xs text-anil-600">{{ $f['clave'] }}</span>@endif
                                    @if ($seccion === 'periodo')
                                        <span class="mt-1 block h-1.5 rounded-full bg-anil-100" aria-hidden="true"><span class="block h-full rounded-full bg-rosa-500" style="width: {{ round(max((float) $f['neto'], 0) / $maximo * 100) }}%"></span></span>
                                    @endif
                                </td>
                                @if (in_array($seccion, ['periodo', 'cajero'], true))<td class="px-3 py-2.5 text-right font-mono">{{ number_format($f['ventas']) }}</td>@endif
                                @if ($seccion === 'articulo')<td class="whitespace-nowrap px-3 py-2.5 text-right font-mono">{{ \App\Support\Numero::cantidad($f['cantidad']) }} {{ $f['unidad'] }}</td>@endif
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono font-semibold">{{ $m($f['neto']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-anil-700">{{ $m($f['costo']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono font-semibold {{ (float) $f['utilidad'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $m($f['utilidad']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-anil-700">{{ $pct($f['margen']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($seccion !== 'articulo')
                        <tfoot>
                            <tr class="border-t-2 border-anil-100 font-extrabold">
                                <td class="px-4 py-3">Total</td>
                                @if (in_array($seccion, ['periodo', 'cajero'], true))<td class="px-3 py-3 text-right font-mono">{{ number_format($filas->sum('ventas')) }}</td>@endif
                                <td class="px-3 py-3 text-right font-mono">{{ $m($filas->sum(fn ($f) => (float) $f['neto'])) }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $m($filas->sum(fn ($f) => (float) $f['costo'])) }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $m($filas->sum(fn ($f) => (float) $f['utilidad'])) }}</td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if ($seccion === 'articulo')<p class="mt-3 text-xs text-anil-600">Se muestran los 100 primeros. Exporta a Excel para ver todos.</p>@endif
        @endif
    @endif

    @if ($seccion === 'pago')
        @php $filas = $this->datos; $total = $filas->sum(fn ($f) => (float) $f['monto']); @endphp
        @if ($filas->isEmpty())
            <x-empty icono="wallet" titulo="Sin ventas en este periodo" texto="Cambia las fechas o los filtros." />
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($filas as $f)
                    <div class="rounded-xl border border-anil-100 bg-white p-4">
                        <p class="rotulo">{{ $f['etiqueta'] }}</p>
                        <p class="mt-1 font-display text-2xl font-extrabold">{{ $m($f['monto']) }}</p>
                        <p class="text-xs text-anil-600">{{ number_format($f['ventas']) }} {{ $f['ventas'] === 1 ? 'venta' : 'ventas' }} · {{ $total > 0 ? round((float) $f['monto'] / $total * 100) : 0 }}%</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-anil-700">Total: <strong>{{ $m($total) }}</strong>. El efectivo no incluye el cambio entregado y «A crédito» es dinero que todavía no se cobra.</p>
        @endif
    @endif

    @if ($seccion === 'inventario')
        @php $v = \App\Services\ReporteVentas::valorInventario(); @endphp
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-kpi etiqueta="Valor a costo" tono="oscuro" tamano="grande" :sombra="false">{{ $m($v['costo']) }}</x-kpi>
            <x-kpi etiqueta="Valor a precio de venta" tamano="grande" :sombra="false">{{ $m($v['venta']) }}</x-kpi>
            <x-kpi etiqueta="Artículos con existencia" tamano="grande" :sombra="false">{{ number_format($v['articulos']) }}</x-kpi>
        </div>
        <div class="mt-6 overflow-x-auto rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead><tr class="rotulo border-b border-anil-100 bg-anil-50/60"><th class="px-4 py-3 font-medium">Departamento</th><th class="px-3 py-3 text-right font-medium">Artículos</th><th class="px-3 py-3 text-right font-medium">A costo</th><th class="px-4 py-3 text-right font-medium">A precio de venta</th></tr></thead>
                <tbody class="divide-y divide-anil-50">
                    @forelse ($v['filas'] as $f)
                        <tr><td class="px-4 py-2.5 font-semibold">{{ $f['nombre'] }}</td><td class="px-3 py-2.5 text-right font-mono">{{ number_format($f['articulos']) }}</td><td class="px-3 py-2.5 text-right font-mono">{{ $m($f['costo']) }}</td><td class="px-4 py-2.5 text-right font-mono">{{ $m($f['venta']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-anil-700">No hay existencias registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-anil-600">Solo cuentan artículos con inventario y existencia mayor que cero, valuados con el costo vigente (según el método de costeo de Configuración).</p>
    @endif
</div>
