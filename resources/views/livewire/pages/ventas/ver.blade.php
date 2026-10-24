<?php

use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Models\Venta;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Venta $venta;

    public string $motivo = '';

    /** @var array<int, string> cantidad a devolver por partida */
    public array $devolver = [];

    public string $motivoDevolucion = '';

    public ?string $exito = null;

    public function mount(Venta $venta): void
    {
        $u = auth()->user();

        abort_unless(
            $u->can('ventas.ver_todas') || ($u->can('ventas.cobrar') && $venta->user_id === $u->id),
            403,
        );

        $this->venta = $venta;
        $this->recargar();
    }

    private function recargar(): void
    {
        $this->venta = Venta::query()->with(['partidas.articulo', 'pagos', 'caja', 'cajero:id,name', 'canceladaPor:id,name', 'devoluciones.usuario:id,name'])->findOrFail($this->venta->id);
    }

    public function cancelar(CancelarVenta $cancelar): void
    {
        $this->resetValidation();

        try {
            $cancelar($this->venta, $this->motivo, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('motivo', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('motivo');
        $this->recargar();
        $this->exito = 'Venta cancelada. El inventario ya se regresó.';
        $this->dispatch('cerrar-modal', nombre: 'cancelar-venta');
    }

    public function registrarDevolucion(RegistrarDevolucion $devolver): void
    {
        $this->resetValidation();

        $items = collect($this->devolver)
            ->filter(fn ($c) => is_numeric($c) && (float) $c > 0)
            ->map(fn ($c, $id) => ['partida_id' => $id, 'cantidad' => (string) $c])
            ->values()->all();

        try {
            $d = $devolver($this->venta, $items, $this->motivoDevolucion, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('devolucion', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('devolver', 'motivoDevolucion');
        $this->recargar();
        $this->exito = "Devolución {$d->folio} registrada por $".number_format((float) $d->total, 2).'. Entrega ese dinero al cliente.';
        $this->dispatch('cerrar-modal', nombre: 'devolucion');
    }
}; ?>

<div>
    <x-slot name="titulo">Venta {{ $venta->folio }}</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('ventas.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver a ventas
        </a>
        <a href="{{ route('ventas.ticket', $venta) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
            <x-phosphor-printer-bold class="size-4" /> Ver / reimprimir ticket
        </a>
    </x-slot>

    @if ($exito)
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ $exito }}
        </p>
    @endif

    @if ($venta->estaCancelada())
        <div class="mb-5 rounded-xl bg-red-50 px-5 py-4 text-red-900 ring-1 ring-red-200" role="status">
            <p class="font-extrabold">Venta cancelada</p>
            <p class="mt-1 text-sm">{{ $venta->cancelada_at->format('d/m/Y H:i') }} por {{ $venta->canceladaPor?->name ?? 'un usuario' }}. Motivo: {{ $venta->motivo_cancelacion }}</p>
        </div>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <x-card titulo="Artículos" :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead>
                            <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                                <th class="px-5 py-3 font-medium">Artículo</th>
                                <th class="px-3 py-3 text-right font-medium">Cant.</th>
                                <th class="px-3 py-3 text-right font-medium">Precio</th>
                                <th class="px-5 py-3 text-right font-medium">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-anil-50">
                            @foreach ($venta->partidas as $p)
                                <tr wire:key="vp-{{ $p->id }}">
                                    <td class="px-5 py-2.5">
                                        <span class="block font-semibold">{{ $p->descripcion }}</span>
                                        <span class="font-mono text-xs text-anil-600">{{ $p->clave }}</span>
                                        @if ((float) $p->descuento > 0)<span class="ml-1 text-xs font-semibold text-emerald-700">Desc. ${{ number_format((float) $p->descuento, 2) }}</span>@endif
                                        @if ((float) $p->cantidad_devuelta > 0)
                                            <span class="mt-0.5 block text-xs font-semibold text-amber-800">Devuelto: {{ rtrim(rtrim((string) $p->cantidad_devuelta, '0'), '.') }} {{ $p->unidad }} (${{ number_format((float) $p->monto_devuelto, 2) }})</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono">{{ rtrim(rtrim((string) $p->cantidad, '0'), '.') }} {{ $p->unidad }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-anil-700">${{ number_format((float) $p->precio_unitario, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-right font-mono font-semibold">${{ number_format((float) $p->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($venta->devoluciones->isNotEmpty())
                <x-card titulo="Devoluciones" :padding="false">
                    <ul class="divide-y divide-anil-50">
                        @foreach ($venta->devoluciones as $d)
                            <li class="flex items-start justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-semibold">{{ $d->folio }}</p>
                                    <p class="text-xs text-anil-600">{{ $d->created_at->format('d/m/Y H:i') }} · {{ $d->usuario?->name }} · {{ $d->motivo }}</p>
                                </div>
                                <p class="shrink-0 font-mono font-semibold text-red-700">−${{ number_format((float) $d->total, 2) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card titulo="Resumen">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-anil-700">Subtotal</dt><dd class="font-mono">${{ number_format((float) $venta->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-anil-700">Impuestos</dt><dd class="font-mono">${{ number_format((float) $venta->impuestos, 2) }}</dd></div>
                    @if ((float) $venta->descuento > 0)<div class="flex justify-between"><dt class="text-anil-700">Descuentos</dt><dd class="font-mono text-emerald-700">−${{ number_format((float) $venta->descuento, 2) }}</dd></div>@endif
                    <div class="flex items-baseline justify-between border-t border-anil-100 pt-2"><dt class="font-extrabold">Total</dt><dd class="font-display text-2xl font-extrabold">${{ number_format((float) $venta->total, 2) }}</dd></div>
                </dl>
                <dl class="mt-4 space-y-1.5 border-t border-anil-50 pt-4 text-sm">
                    @php $formas = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'credito' => 'Crédito']; @endphp
                    @foreach ($venta->pagos as $pago)
                        <div class="flex justify-between"><dt class="text-anil-700">{{ $formas[$pago->forma] ?? $pago->forma }}{{ $pago->referencia ? ' · '.$pago->referencia : '' }}</dt><dd class="font-mono">${{ number_format((float) $pago->monto, 2) }}</dd></div>
                    @endforeach
                    @if ((float) $venta->cambio > 0)
                        <div class="flex justify-between"><dt class="text-anil-700">Cambio entregado</dt><dd class="font-mono">${{ number_format((float) $venta->cambio, 2) }}</dd></div>
                    @endif
                </dl>
            </x-card>

            <x-card titulo="Datos">
                <dl class="space-y-2 text-sm">
                    <div><dt class="rotulo">Fecha</dt><dd>{{ $venta->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="rotulo">Caja</dt><dd>{{ $venta->caja->nombre }}</dd></div>
                    <div><dt class="rotulo">Atendió</dt><dd>{{ $venta->cajero?->name ?? '—' }}</dd></div>
                </dl>
            </x-card>

            @if (! $venta->estaCancelada())
                <div class="flex flex-wrap gap-3">
                    @can('ventas.devolver')
                        <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'devolucion' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                            <x-phosphor-arrow-u-up-left-bold class="size-4" /> Devolución
                        </button>
                    @endcan
                    @can('ventas.cancelar')
                        @if ($venta->devoluciones->isEmpty())
                            <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'cancelar-venta' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm font-semibold text-peligro hover:bg-red-50">
                                <x-phosphor-prohibit-bold class="size-4" /> Cancelar venta
                            </button>
                        @endif
                    @endcan
                </div>
            @endif
        </div>
    </div>

    @can('ventas.cancelar')
        <x-modal nombre="cancelar-venta" titulo="Cancelar venta">
            <form wire:submit="cancelar" class="space-y-4">
                <p class="text-sm text-anil-800">Los artículos regresan al inventario. La venta no se borra: queda marcada como cancelada con tu nombre y el motivo.</p>
                <div>
                    <x-input-label for="motivo" value="Motivo" />
                    <x-text-input wire:model="motivo" id="motivo" type="text" maxlength="255" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('motivo')" class="mt-1.5" />
                </div>
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('cerrar-modal')">No cancelar</x-secondary-button>
                    <x-primary-button>Cancelar venta</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan

    @can('ventas.devolver')
        <x-modal nombre="devolucion" titulo="Devolución" max-width="lg">
            <form wire:submit="registrarDevolucion" class="space-y-4">
                <p class="text-sm text-anil-800">Escribe cuántas piezas regresan. Se reembolsa lo que el cliente realmente pagó (con su descuento).</p>
                <ul class="divide-y divide-anil-50">
                    @foreach ($venta->partidas as $p)
                        @php $max = $p->cantidadDevolvible(); @endphp
                        <li class="flex items-center gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $p->descripcion }}</p>
                                <p class="text-xs text-anil-600">Se pueden devolver {{ rtrim(rtrim($max, '0'), '.') ?: '0' }} {{ $p->unidad }}</p>
                            </div>
                            <x-text-input wire:model="devolver.{{ $p->id }}" type="text" inputmode="decimal" class="!w-24 text-center font-mono" placeholder="0" :disabled="(float) $max <= 0" aria-label="Cantidad a devolver de {{ $p->descripcion }}" />
                        </li>
                    @endforeach
                </ul>
                <div>
                    <x-input-label for="motivoDevolucion" value="Motivo" />
                    <x-text-input wire:model="motivoDevolucion" id="motivoDevolucion" type="text" maxlength="255" required autocomplete="off" />
                </div>
                <x-input-error :messages="$errors->get('devolucion')" />
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                    <x-primary-button>Registrar devolución</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</div>
