@props(['resumen', 'mostrarEsperado' => true, 'contado' => null, 'diferencia' => null])

@php
    $m = fn ($v) => '$'.number_format((float) $v, 2);
    $abonosTotal = array_sum(array_map('floatval', $resumen['abonos'] ?? []));
@endphp

<div {{ $attributes->class(['space-y-5']) }}>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-anil-100 bg-white p-4">
            <p class="rotulo">Ventas</p>
            <p class="mt-1 font-display text-2xl font-extrabold">{{ number_format($resumen['ventas']['cantidad']) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4">
            <p class="rotulo">Vendido</p>
            <p class="mt-1 font-display text-2xl font-extrabold">{{ $m($resumen['ventas']['total']) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4">
            <p class="rotulo">Fondo inicial</p>
            <p class="mt-1 font-display text-2xl font-extrabold">{{ $m($resumen['fondo_inicial']) }}</p>
        </div>
        @if ($mostrarEsperado)
            <div class="rounded-xl border border-anil-200 bg-anil-900 p-4 text-white">
                <p class="rotulo !text-anil-300">Efectivo esperado</p>
                <p class="mt-1 font-display text-2xl font-extrabold">{{ $m($resumen['efectivo_esperado']) }}</p>
            </div>
        @endif
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <dl class="space-y-2 rounded-xl border border-anil-100 bg-white p-4 text-sm">
            <p class="rotulo mb-2">Cobrado por forma de pago</p>
            @foreach (\App\Enums\FormaPago::cases() as $forma)
                <div class="flex justify-between"><dt class="text-anil-700">{{ $forma->etiquetaCorte() }}</dt><dd class="font-mono font-semibold">{{ $m($resumen['pagos'][$forma->value] ?? 0) }}</dd></div>
            @endforeach
            <div class="flex justify-between border-t border-anil-100 pt-2"><dt class="font-semibold">Total vendido</dt><dd class="font-mono font-extrabold">{{ $m($resumen['ventas']['total']) }}</dd></div>
            @if ($abonosTotal > 0)
                <div class="flex justify-between pt-1"><dt class="text-anil-700">Abonos de clientes</dt><dd class="font-mono font-semibold">{{ $m($abonosTotal) }}</dd></div>
            @endif
            <p class="pt-1 text-xs text-anil-600">Incluye {{ $m($resumen['ventas']['impuestos']) }} de impuestos y descuentos por {{ $m($resumen['ventas']['descuento']) }}.</p>
        </dl>

        <dl class="space-y-2 rounded-xl border border-anil-100 bg-white p-4 text-sm">
            <p class="rotulo mb-2">Movimientos del cajón</p>
            <div class="flex justify-between"><dt class="text-anil-700">Fondo inicial</dt><dd class="font-mono">{{ $m($resumen['fondo_inicial']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-anil-700">+ Efectivo de ventas</dt><dd class="font-mono">{{ $m($resumen['pagos']['efectivo']) }}</dd></div>
            @if ((float) ($resumen['abonos']['efectivo'] ?? 0) > 0)
                <div class="flex justify-between"><dt class="text-anil-700">+ Abonos en efectivo</dt><dd class="font-mono">{{ $m($resumen['abonos']['efectivo']) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-anil-700">+ Entradas de efectivo</dt><dd class="font-mono">{{ $m($resumen['entradas']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-anil-700">− Retiros</dt><dd class="font-mono">{{ $m($resumen['retiros']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-anil-700">− Devoluciones ({{ $resumen['devoluciones']['cantidad'] }})</dt><dd class="font-mono">{{ $m($resumen['devoluciones']['efectivo'] ?? $resumen['devoluciones']['total']) }}</dd></div>
            @if ($mostrarEsperado)
                <div class="flex justify-between border-t border-anil-100 pt-2"><dt class="font-semibold">Efectivo esperado</dt><dd class="font-mono font-extrabold">{{ $m($resumen['efectivo_esperado']) }}</dd></div>
            @endif
            @if ($contado !== null)
                <div class="flex justify-between"><dt class="text-anil-700">Efectivo contado</dt><dd class="font-mono font-semibold">{{ $m($contado) }}</dd></div>
                @php $d = (float) $diferencia; @endphp
                <div @class(['flex justify-between rounded-lg px-3 py-2 font-extrabold', 'bg-emerald-50 text-emerald-900' => $d == 0, 'bg-etiqueta/30 text-amber-900' => $d > 0, 'bg-red-50 text-red-800' => $d < 0])>
                    <dt>{{ $d == 0 ? 'Caja cuadrada' : ($d > 0 ? 'Sobrante' : 'Faltante') }}</dt>
                    <dd class="font-mono">{{ $d > 0 ? '+' : '' }}{{ $m($d) }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($resumen['canceladas']['cantidad'] > 0)
        <p class="text-sm text-anil-700">
            Ventas canceladas en este turno: <strong>{{ $resumen['canceladas']['cantidad'] }}</strong> por {{ $m($resumen['canceladas']['total']) }} (no se cuentan en lo cobrado).
        </p>
    @endif
</div>
