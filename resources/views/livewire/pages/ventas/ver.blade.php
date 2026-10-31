<?php

use App\Actions\Usuarios\VerificarAutorizacion;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Models\Turno;
use App\Models\Venta;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Venta $venta;

    public string $motivo = '';

    /** Usuario y contraseña del supervisor que autoriza la cancelación (cuando quien cancela no tiene el permiso). */
    public string $supervisor = '';

    public string $claveSupervisor = '';

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
        unset($this->puedeCancelarDirecto, $this->puedePedirAutorizacion, $this->turnoAbierto);

        $this->venta = Venta::query()->with(['partidas.articulo', 'pagos', 'caja', 'cajero:id,name', 'canceladaPor:id,name', 'autorizadaPor:id,name', 'devoluciones.usuario:id,name'])->findOrFail($this->venta->id);
    }

    /** Quien tiene el permiso cancela directo; un cajero necesita que un supervisor lo autorice. */
    #[Computed]
    public function puedeCancelarDirecto(): bool
    {
        return auth()->user()->can('ventas.cancelar');
    }

    /** Un cajero puede pedir autorización para cancelar su propia venta mientras su turno siga abierto. */
    #[Computed]
    public function puedePedirAutorizacion(): bool
    {
        $u = auth()->user();

        return ! $this->puedeCancelarDirecto()
            && $u->can('ventas.cobrar')
            && $this->venta->user_id === $u->id
            && ! $this->venta->estaCancelada()
            && $this->venta->devoluciones->isEmpty()
            && $this->turnoAbierto;
    }

    /** El turno de la venta sigue abierto (si no, la cancelación se hace como devolución total). */
    #[Computed]
    public function turnoAbierto(): bool
    {
        return ! $this->venta->turno_id || Turno::query()->abiertos()->whereKey($this->venta->turno_id)->exists();
    }

    public function cancelar(CancelarVenta $cancelar, VerificarAutorizacion $verificar): void
    {
        $this->resetValidation();
        $usuario = auth()->user();

        try {
            $autorizo = null;

            if (! $this->puedeCancelarDirecto) {
                abort_unless($this->puedePedirAutorizacion, 403, 'No tienes permiso para cancelar ventas.');

                $autorizo = $verificar($this->supervisor, $this->claveSupervisor, 'ventas.autorizar_cancelacion', $usuario);
            }

            $cancelar($this->venta, $this->motivo, $usuario, $autorizo);
        } catch (ValidationException $e) {
            // Los datos del supervisor nunca se quedan en la pantalla después de un intento.
            $this->reset('claveSupervisor');
            $this->addError(isset($e->errors()['autorizacion']) ? 'autorizacion' : 'motivo', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('motivo', 'supervisor', 'claveSupervisor');
        $this->recargar();
        $this->exito = $this->venta->cancelacionTardia()
            ? 'Venta cancelada con una devolución total ('.$this->venta->cancelacionTardia()->folio.'). Entrega al cliente $'.number_format((float) $this->venta->cancelacionTardia()->total, 2).' en lo que haya pagado en efectivo; el corte de aquel turno no cambia.'
            : 'Venta cancelada. El inventario ya se regresó.';
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
        <x-enlace-volver :href="route('ventas.index')">Volver a ventas</x-enlace-volver>
        <a href="{{ route('ventas.ticket', $venta) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
            <x-phosphor-printer-bold class="size-4" /> Ver / reimprimir ticket
        </a>
    </x-slot>

    @if ($exito)
        <x-alerta class="mb-5">{{ $exito }}</x-alerta>
    @endif

    @if ($venta->estaCancelada())
        <div class="mb-5 rounded-xl bg-red-50 px-5 py-4 text-red-900 ring-1 ring-red-200" role="status">
            <p class="font-extrabold">Venta cancelada</p>
            <p class="mt-1 text-sm">
                {{ $venta->cancelada_at->format('d/m/Y H:i') }} por {{ $venta->canceladaPor?->name ?? 'un usuario' }}.
                @if ($venta->autorizada_por && $venta->autorizada_por !== $venta->cancelada_por)Autorizó: {{ $venta->autorizadaPor?->name ?? 'un supervisor' }}.@endif
                Motivo: {{ $venta->motivo_cancelacion }}
            </p>
        </div>
    @elseif ($venta->cancelacionTardia())
        @php $tardia = $venta->cancelacionTardia(); @endphp
        <div class="mb-5 rounded-xl bg-red-50 px-5 py-4 text-red-900 ring-1 ring-red-200" role="status">
            <p class="font-extrabold">Cancelada con devolución total</p>
            <p class="mt-1 text-sm">
                {{ $tardia->created_at->format('d/m/Y H:i') }} por {{ $tardia->usuario?->name ?? 'un usuario' }}
                ({{ $tardia->folio }}, ${{ number_format((float) $tardia->total, 2) }}).
                @if ($venta->autorizada_por && $venta->autorizada_por !== $tardia->user_id)Autorizó: {{ $venta->autorizadaPor?->name ?? 'un supervisor' }}.@endif
                Motivo: {{ $tardia->motivo }}. El turno de la venta ya estaba cerrado: su corte no cambió.
            </p>
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
                                            <span class="mt-0.5 block text-xs font-semibold text-amber-800">Devuelto: {{ \App\Support\Numero::cantidad($p->cantidad_devuelta) }} {{ $p->unidad }} (${{ number_format((float) $p->monto_devuelto, 2) }})</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono">{{ \App\Support\Numero::cantidad($p->cantidad) }} {{ $p->unidad }}</td>
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
                    @foreach ($venta->pagos as $pago)
                        <div class="flex justify-between"><dt class="text-anil-700">{{ \App\Enums\FormaPago::etiquetaDe($pago->forma) }}{{ $pago->referencia ? ' · '.$pago->referencia : '' }}</dt><dd class="font-mono">${{ number_format((float) $pago->monto, 2) }}</dd></div>
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

            @if (! $venta->estaCancelada() && ! $venta->cancelacionTardia())
                <div class="flex flex-wrap gap-3">
                    @can('ventas.devolver')
                        <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'devolucion' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                            <x-phosphor-arrow-u-up-left-bold class="size-4" /> Devolución
                        </button>
                    @endcan
                    @if ($venta->devoluciones->isEmpty() && ($this->puedeCancelarDirecto || $this->puedePedirAutorizacion))
                        <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'cancelar-venta' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm font-semibold text-peligro hover:bg-red-50">
                            <x-phosphor-prohibit-bold class="size-4" /> {{ $this->puedeCancelarDirecto ? 'Cancelar venta' : 'Pedir autorización para cancelar' }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($this->puedeCancelarDirecto || $this->puedePedirAutorizacion)
        <x-modal nombre="cancelar-venta" :titulo="$this->puedeCancelarDirecto ? 'Cancelar venta' : 'Pedir autorización para cancelar'">
            <form wire:submit="cancelar" class="space-y-4" autocomplete="off">
                @if (! $this->puedeCancelarDirecto)
                    <p class="text-sm text-anil-800">Para cancelar esta venta necesitas que un <strong>supervisor</strong> la autorice: escribe el motivo y pídele que ponga su usuario y su contraseña aquí. Los artículos regresan al inventario y queda anotado quién la autorizó.</p>
                @elseif (! $this->turnoAbierto)
                    <p class="text-sm text-anil-800">El turno de esta venta ya se cerró y su corte no se puede cambiar. Se hará una <strong>devolución total</strong>: los artículos regresan al inventario y el dinero cobrado en efectivo sale de <strong>tu caja abierta</strong> (necesitas tener tu turno abierto). La venta queda marcada como cancelada con tu nombre y el motivo.</p>
                @else
                    <p class="text-sm text-anil-800">Los artículos regresan al inventario. La venta no se borra: queda marcada como cancelada con tu nombre y el motivo.</p>
                @endif
                <div>
                    <x-input-label for="motivo" value="Motivo" />
                    <x-text-input wire:model="motivo" id="motivo" type="text" maxlength="255" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('motivo')" class="mt-1.5" />
                </div>
                @if (! $this->puedeCancelarDirecto)
                    <fieldset class="space-y-3 rounded-lg bg-anil-50 p-4">
                        <legend class="rotulo px-1">Autoriza el supervisor</legend>
                        <div>
                            <x-input-label for="supervisor" value="Usuario del supervisor" />
                            <x-text-input wire:model="supervisor" id="supervisor" type="text" required autocomplete="off" autocapitalize="off" spellcheck="false" />
                        </div>
                        <div>
                            <x-input-label for="claveSupervisor" value="Contraseña del supervisor" />
                            <x-text-input wire:model="claveSupervisor" id="claveSupervisor" type="password" required autocomplete="new-password" />
                        </div>
                        <x-input-error :messages="$errors->get('autorizacion')" />
                    </fieldset>
                @endif
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('cerrar-modal')">No cancelar</x-secondary-button>
                    <x-primary-button>{{ $this->puedeCancelarDirecto ? 'Cancelar venta' : 'Autorizar y cancelar' }}</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

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
                                <p class="text-xs text-anil-600">Se pueden devolver {{ \App\Support\Numero::cantidad($max) }} {{ $p->unidad }}</p>
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
