<?php

use App\Actions\Clientes\RegistrarAbono;
use App\Models\Cliente;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public Cliente $cliente;

    public string $monto = '';

    public string $forma = 'efectivo';

    public string $referencia = '';

    public ?string $exito = null;

    public function mount(Cliente $cliente): void
    {
        $this->authorize('clientes.ver');
        $this->cliente = $cliente;
    }

    #[Computed]
    public function movimientos()
    {
        return $this->cliente->movimientos()->with(['usuario:id,name', 'venta:id,folio'])->paginate(20);
    }

    public function abonar(RegistrarAbono $abonar): void
    {
        $this->resetValidation();

        try {
            $m = $abonar($this->cliente, trim($this->monto), $this->forma, $this->referencia, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('monto', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->cliente->refresh();
        $this->exito = 'Abono de $'.number_format((float) $m->monto, 2).' registrado. Ahora debe $'.number_format((float) $m->saldo_resultante, 2).'.';
        $this->reset('monto', 'referencia');
        $this->forma = 'efectivo';
        unset($this->movimientos);
        $this->dispatch('cerrar-modal', nombre: 'abono');
    }
}; ?>

<div>
    <x-slot name="titulo">{{ $cliente->nombre }}</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('clientes.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Todos los clientes
        </a>
        <a href="{{ route('clientes.estado', $cliente) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
            <x-phosphor-printer-bold class="size-4" /> Imprimir estado de cuenta
        </a>
    </x-slot>

    @if ($exito)
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status"><x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ $exito }}</p>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div @class(['rounded-xl border p-4', 'border-red-200 bg-red-50' => (float) $cliente->saldo > 0, 'border-anil-100 bg-white' => (float) $cliente->saldo <= 0])>
            <p class="rotulo">Debe</p>
            <p class="mt-1 font-display text-2xl font-extrabold">${{ number_format((float) $cliente->saldo, 2) }}</p>
        </div>
        <div class="rounded-xl border border-anil-100 bg-white p-4"><p class="rotulo">Límite de crédito</p><p class="mt-1 font-display text-2xl font-extrabold">${{ number_format((float) $cliente->limite_credito, 2) }}</p></div>
        <div class="rounded-xl border border-anil-100 bg-white p-4"><p class="rotulo">Disponible</p><p class="mt-1 font-display text-2xl font-extrabold">${{ number_format((float) $cliente->disponible(), 2) }}</p></div>
        <div class="rounded-xl border border-anil-100 bg-white p-4"><p class="rotulo">Lista de precios</p><p class="mt-1 font-display text-2xl font-extrabold">Precio {{ $cliente->lista_precios }}</p></div>
    </div>

    <p class="mb-6 text-sm text-anil-700">
        {{ collect([$cliente->telefono, $cliente->correo, $cliente->direccion])->filter()->implode(' · ') ?: 'Sin datos de contacto.' }}
        @if ($cliente->notas)<span class="block pt-1">{{ $cliente->notas }}</span>@endif
    </p>

    @can('clientes.abonar')
        @if ((float) $cliente->saldo > 0)
            <div class="mb-6">
                <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'abono' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-rosa-500 px-5 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none">
                    <x-phosphor-hand-coins-bold class="size-5" /> Registrar abono
                </button>
            </div>
        @endif
    @endcan

    <x-card titulo="Movimientos de la cuenta" :padding="false">
        @php $lista = $this->movimientos; @endphp
        @if ($lista->isEmpty())
            <div class="p-5"><p class="text-sm text-anil-700">Este cliente todavía no tiene movimientos de crédito.</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead>
                        <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                            <th class="px-5 py-3 font-medium">Fecha</th>
                            <th class="px-3 py-3 font-medium">Movimiento</th>
                            <th class="px-3 py-3 text-right font-medium">Importe</th>
                            <th class="px-5 py-3 text-right font-medium">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-anil-50">
                        @foreach ($lista as $m)
                            <tr wire:key="mv-{{ $m->id }}">
                                <td class="whitespace-nowrap px-5 py-2.5 font-mono text-xs text-anil-700">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="font-semibold">{{ $m->etiqueta() }}</span>
                                    @if ($m->venta)<span class="font-mono text-xs text-anil-600"> · {{ $m->venta->folio }}</span>@endif
                                    @if ($m->forma)<span class="text-xs text-anil-600"> · {{ ucfirst($m->forma) }}{{ $m->referencia ? ' ('.$m->referencia.')' : '' }}</span>@endif
                                    <span class="block text-xs text-anil-500">{{ $m->usuario?->name }}{{ $m->nota ? ' · '.$m->nota : '' }}</span>
                                </td>
                                <td @class(['whitespace-nowrap px-3 py-2.5 text-right font-mono font-semibold', 'text-red-700' => $m->esCargo(), 'text-emerald-700' => ! $m->esCargo()])>{{ $m->esCargo() ? '+' : '−' }}${{ number_format((float) $m->monto, 2) }}</td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-right font-mono">${{ number_format((float) $m->saldo_resultante, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-anil-50 p-4">{{ $lista->links() }}</div>
        @endif
    </x-card>

    @can('clientes.abonar')
        <x-modal nombre="abono" titulo="Registrar abono">
            <form wire:submit="abonar" class="space-y-5">
                <p class="text-sm text-anil-800">{{ $cliente->nombre }} debe <strong>${{ number_format((float) $cliente->saldo, 2) }}</strong>.</p>
                <div>
                    <x-input-label for="ab_monto" value="Monto del abono" />
                    <x-text-input wire:model="monto" id="ab_monto" type="text" inputmode="decimal" class="font-mono text-2xl" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('monto')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="ab_forma" value="Forma de pago" />
                    <x-select wire:model="forma" id="ab_forma">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                    </x-select>
                    <p class="mt-1 text-xs text-anil-600">El efectivo entra al cajón de tu turno y aparece en tu corte.</p>
                </div>
                <div>
                    <x-input-label for="ab_ref" value="Referencia (opcional)" />
                    <x-text-input wire:model="referencia" id="ab_ref" type="text" maxlength="60" autocomplete="off" />
                </div>
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                    <x-primary-button>Registrar abono</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</div>
