<?php

use App\Actions\Compras\CancelarCompra;
use App\Models\Compra;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Compra $compra;

    public string $motivo = '';

    public function mount(Compra $compra): void
    {
        $this->authorize('compras.ver');
        $this->compra = $compra->load(['proveedor', 'usuario:id,name', 'canceladaPor:id,name', 'partidas.articulo.unidad']);
    }

    public function cancelar(CancelarCompra $cancelar): void
    {
        $this->authorize('compras.cancelar');
        $this->resetValidation();

        try {
            $this->compra = $cancelar($this->compra, $this->motivo, auth()->user())
                ->load(['proveedor', 'usuario:id,name', 'canceladaPor:id,name', 'partidas.articulo.unidad']);
        } catch (ValidationException $e) {
            $this->addError('motivo', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('motivo');
        $this->dispatch('cerrar-modal', nombre: 'cancelar-compra');
    }

    public function verCostos(): bool
    {
        return auth()->user()->can('articulos.editar_precio');
    }
}; ?>

<div>
    <x-slot name="titulo">Compra {{ $compra->folio }}</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('compras.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver a compras
        </a>
    </x-slot>

    @if (session('exito'))
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ session('exito') }}
        </p>
    @endif

    @if ($compra->estaCancelada())
        <div class="mb-5 rounded-xl bg-red-50 px-5 py-4 text-red-900 ring-1 ring-red-200" role="status">
            <p class="font-extrabold">Compra cancelada</p>
            <p class="mt-1 text-sm">
                {{ $compra->cancelada_at->format('d/m/Y H:i') }} por {{ $compra->canceladaPor?->name ?? 'un usuario' }}. Motivo: {{ $compra->motivo_cancelacion }}
            </p>
            <p class="mt-1 text-sm">La mercancía se descontó del inventario.</p>
        </div>
    @endif

    <x-card class="mb-6">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="rotulo">Proveedor</dt><dd class="mt-0.5 font-semibold">{{ $compra->proveedor->nombre }}</dd></div>
            <div><dt class="rotulo">Fecha</dt><dd class="mt-0.5 font-semibold">{{ $compra->fecha->translatedFormat('j \d\e F Y') }}</dd></div>
            <div><dt class="rotulo">Factura o remisión</dt><dd class="mt-0.5 font-mono font-semibold">{{ $compra->factura ?: '—' }}</dd></div>
            <div><dt class="rotulo">Registró</dt><dd class="mt-0.5 font-semibold">{{ $compra->usuario?->name ?? '—' }}</dd></div>
        </dl>
        @if ($compra->notas)
            <p class="mt-4 border-t border-anil-50 pt-4 text-sm text-anil-800">{{ $compra->notas }}</p>
        @endif
    </x-card>

    <x-card titulo="Mercancía" :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead>
                    <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                        <th class="px-5 py-3 font-medium">Artículo</th>
                        <th class="px-3 py-3 text-right font-medium">Cantidad</th>
                        <th class="px-3 py-3 text-right font-medium">Costo</th>
                        <th class="px-5 py-3 text-right font-medium">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-anil-50">
                    @foreach ($compra->partidas as $p)
                        <tr wire:key="p-{{ $p->id }}">
                            <td class="px-5 py-2.5">
                                <span class="block font-semibold">{{ $p->descripcion }}</span>
                                <span class="font-mono text-xs text-anil-600">{{ $p->clave }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono">{{ rtrim(rtrim((string) $p->cantidad, '0'), '.') }} {{ $p->articulo->unidad->abreviatura }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-anil-700">${{ number_format((float) $p->costo_unitario, 2) }}</td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right font-mono font-semibold">${{ number_format((float) $p->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-anil-100">
                        <td colspan="3" class="px-5 py-3 text-right font-extrabold">Total (sin impuestos)</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-display text-lg font-extrabold">${{ number_format((float) $compra->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>

    @can('compras.cancelar')
        @if (! $compra->estaCancelada())
            <div class="mt-6">
                <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'cancelar-compra' })"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm font-semibold text-peligro hover:bg-red-50">
                    <x-phosphor-prohibit-bold class="size-4" /> Cancelar compra
                </button>
            </div>

            <x-modal nombre="cancelar-compra" titulo="Cancelar compra">
                <form wire:submit="cancelar" class="space-y-4">
                    <p class="text-sm text-anil-800">La mercancía de esta compra se descontará del inventario. La compra no se borra: queda marcada como cancelada.</p>
                    <div>
                        <x-input-label for="motivo" value="Motivo" />
                        <x-text-input wire:model="motivo" id="motivo" type="text" maxlength="255" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('motivo')" class="mt-1.5" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('cerrar-modal')">No cancelar</x-secondary-button>
                        <x-primary-button>Cancelar compra</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endcan
</div>
