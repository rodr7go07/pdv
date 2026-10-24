<?php

use App\Actions\Caja\AbrirTurno;
use App\Actions\Caja\CerrarTurno;
use App\Actions\Caja\RegistrarMovimientoCaja;
use App\Models\Caja;
use App\Models\Corte;
use App\Models\Turno;
use App\Services\ResumenTurno;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $cajaElegida = '';

    public string $fondo = '';

    public string $tipoMovimiento = 'retiro';

    public string $monto = '';

    public string $motivo = '';

    public string $contado = '';

    public string $notas = '';

    public ?int $corteReciente = null;

    public ?string $aviso = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['caja.abrir', 'caja.corte']), 403);
    }

    #[Computed]
    public function turno(): ?Turno
    {
        return Turno::abiertoDe(auth()->user());
    }

    #[Computed]
    public function cajasLibres()
    {
        return Caja::query()->activas()
            ->whereNotIn('id', Turno::query()->abiertos()->select('caja_id'))
            ->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function resumen(): ?array
    {
        return $this->turno ? ResumenTurno::calcular($this->turno) : null;
    }

    #[Computed]
    public function veEsperado(): bool
    {
        return auth()->user()->can('caja.supervisar');
    }

    #[Computed]
    public function movimientos()
    {
        return $this->turno ? $this->turno->movimientos()->with('usuario:id,name')->get() : collect();
    }

    #[Computed]
    public function cortes()
    {
        return $this->turno ? $this->turno->cortes()->get() : collect();
    }

    private function refrescar(): void
    {
        unset($this->turno, $this->resumen, $this->movimientos, $this->cortes, $this->cajasLibres);
    }

    public function abrir(AbrirTurno $abrir): void
    {
        $this->resetValidation();

        $caja = Caja::query()->find((int) $this->cajaElegida);

        if (! $caja) {
            $this->addError('cajaElegida', 'Elige la caja que vas a usar.');

            return;
        }

        try {
            $abrir($caja, auth()->user(), $this->fondo);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo === 'caja' ? 'cajaElegida' : ($campo === 'fondo_inicial' ? 'fondo' : $campo), $mensajes[0]);
            }

            return;
        }

        $this->reset('fondo', 'cajaElegida');
        $this->refrescar();
        $this->aviso = 'Turno abierto. Ya puedes vender.';
    }

    public function registrarMovimiento(RegistrarMovimientoCaja $registrar): void
    {
        $this->resetValidation();

        try {
            $registrar($this->turno, $this->tipoMovimiento, trim($this->monto), $this->motivo, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo === 'turno' ? 'monto' : $campo, $mensajes[0]);
            }

            return;
        }

        $this->aviso = $this->tipoMovimiento === 'retiro' ? 'Retiro registrado.' : 'Entrada registrada.';
        $this->reset('monto', 'motivo');
        $this->refrescar();
        $this->dispatch('cerrar-modal', nombre: 'movimiento');
    }

    public function corteX(CerrarTurno $cerrar): void
    {
        $corte = $cerrar->corteX($this->turno, auth()->user());

        $this->corteReciente = $corte->id;
        $this->aviso = 'Corte parcial generado.';
        $this->refrescar();
    }

    public function cerrarTurno(CerrarTurno $cerrar): void
    {
        $this->resetValidation();

        try {
            $corte = $cerrar->cerrar($this->turno, trim($this->contado), auth()->user(), $this->notas);
        } catch (ValidationException $e) {
            $this->addError('contado', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('contado', 'notas');
        $this->corteReciente = $corte->id;
        $this->aviso = 'Turno cerrado. Revisa el corte final.';
        $this->refrescar();
        $this->dispatch('cerrar-modal', nombre: 'cerrar-turno');
    }

    #[Computed]
    public function ultimoCorte(): ?Corte
    {
        return $this->corteReciente ? Corte::query()->find($this->corteReciente) : null;
    }
}; ?>

<div>
    <x-slot name="titulo">Mi caja</x-slot>
    <x-slot name="acciones">
        @can('ventas.cobrar')
            @if ($this->turno)
                <a href="{{ route('pos') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-rosa-500 px-4 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)] hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none">
                    <x-phosphor-cash-register-bold class="size-4" /> Ir a vender
                </a>
            @endif
        @endcan
    </x-slot>

    @if ($aviso)
        <p class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <span class="flex items-center gap-2"><x-phosphor-check-circle-bold class="size-5 shrink-0" /> {{ $aviso }}</span>
            @if ($this->ultimoCorte)
                <a href="{{ route('caja.corte', $this->ultimoCorte) }}" target="_blank" rel="noopener" class="font-semibold underline">Ver e imprimir el corte</a>
            @endif
        </p>
    @endif

    @if (! $this->turno)
        <div class="max-w-xl">
            <x-card titulo="Abrir turno" descripcion="Elige tu caja y cuenta el efectivo con el que empiezas (el fondo).">
                @can('caja.abrir')
                    @if ($this->cajasLibres->isEmpty())
                        <x-empty icono="cash-register" titulo="No hay cajas libres" texto="Todas las cajas activas tienen un turno abierto. Pídele al administrador que active otra caja." />
                    @else
                        <form wire:submit="abrir" class="space-y-5">
                            <div>
                                <x-input-label for="caja_elegida" value="Caja" />
                                <x-select wire:model="cajaElegida" id="caja_elegida" required>
                                    <option value="">Elige una caja…</option>
                                    @foreach ($this->cajasLibres as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                                </x-select>
                                <x-input-error :messages="$errors->get('cajaElegida')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="fondo" value="Fondo inicial (efectivo en el cajón)" />
                                <x-text-input wire:model="fondo" id="fondo" type="text" inputmode="decimal" class="font-mono text-lg" placeholder="0.00" autocomplete="off" />
                                <x-input-error :messages="$errors->get('fondo')" class="mt-1.5" />
                            </div>
                            <x-primary-button class="w-full">Abrir turno</x-primary-button>
                        </form>
                    @endif
                @else
                    <p class="text-sm text-anil-700">No tienes permiso para abrir turnos.</p>
                @endcan
            </x-card>
        </div>
    @else
        @php $t = $this->turno; $r = $this->resumen; @endphp

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-anil-100 bg-white px-5 py-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
            <div>
                <p class="font-display text-xl font-extrabold">{{ $t->caja->nombre }}</p>
                <p class="text-sm text-anil-700">Abierto desde el {{ $t->abierto_at->translatedFormat('j \d\e F, H:i') }} por {{ $t->usuario->name }}</p>
            </div>
            <x-badge tono="ok">Turno abierto</x-badge>
        </div>

        <x-resumen-turno :resumen="$r" :mostrar-esperado="$this->veEsperado" />

        <div class="mt-6 flex flex-wrap gap-3">
            @can('caja.retiro')
                <button type="button" wire:click="$set('tipoMovimiento', 'entrada')" x-on:click="$dispatch('abrir-modal', { nombre: 'movimiento' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                    <x-phosphor-arrow-circle-down-bold class="size-5" /> Entrada de efectivo
                </button>
                <button type="button" wire:click="$set('tipoMovimiento', 'retiro')" x-on:click="$dispatch('abrir-modal', { nombre: 'movimiento' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                    <x-phosphor-arrow-circle-up-bold class="size-5" /> Retiro de efectivo
                </button>
            @endcan
            @can('caja.corte')
                <button type="button" wire:click="corteX" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                    <x-phosphor-file-text-bold class="size-5" /> Corte parcial (X)
                </button>
                <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'cerrar-turno' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-anil-900 px-4 text-sm font-semibold text-white hover:bg-anil-800">
                    <x-phosphor-lock-key-bold class="size-5" /> Cerrar turno
                </button>
            @endcan
        </div>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
            <x-card titulo="Entradas y retiros" :padding="false">
                @if ($this->movimientos->isEmpty())
                    <div class="p-5"><p class="text-sm text-anil-700">Todavía no hay entradas ni retiros en este turno.</p></div>
                @else
                    <ul class="divide-y divide-anil-50">
                        @foreach ($this->movimientos as $m)
                            <li wire:key="mc-{{ $m->id }}" class="flex items-start justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $m->tipo === 'retiro' ? 'Retiro' : 'Entrada' }}</p>
                                    <p class="text-xs text-anil-600">{{ $m->motivo }} · {{ $m->usuario?->name }} · {{ $m->created_at->format('H:i') }}</p>
                                </div>
                                <p @class(['shrink-0 font-mono font-semibold', 'text-red-700' => $m->tipo === 'retiro', 'text-emerald-700' => $m->tipo === 'entrada'])>{{ $m->tipo === 'retiro' ? '−' : '+' }}${{ number_format((float) $m->monto, 2) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card titulo="Cortes de este turno" :padding="false">
                @if ($this->cortes->isEmpty())
                    <div class="p-5"><p class="text-sm text-anil-700">Aún no hay cortes. El corte parcial (X) no cierra el turno.</p></div>
                @else
                    <ul class="divide-y divide-anil-50">
                        @foreach ($this->cortes as $c)
                            <li wire:key="co-{{ $c->id }}">
                                <a href="{{ route('caja.corte', $c) }}" target="_blank" rel="noopener" class="flex min-h-14 items-center justify-between gap-3 px-5 py-3 hover:bg-anil-50/60">
                                    <span><span class="block font-semibold">{{ $c->etiqueta() }}</span><span class="text-xs text-anil-600">{{ $c->created_at->format('d/m/Y H:i') }}</span></span>
                                    <x-phosphor-printer-bold class="size-5 text-anil-500" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        @can('caja.retiro')
            <x-modal nombre="movimiento" :titulo="$tipoMovimiento === 'retiro' ? 'Retiro de efectivo' : 'Entrada de efectivo'">
                <form wire:submit="registrarMovimiento" class="space-y-5">
                    <div>
                        <x-input-label for="mov_monto" value="Monto" />
                        <x-text-input wire:model="monto" id="mov_monto" type="text" inputmode="decimal" class="font-mono text-lg" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('monto')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="mov_motivo" value="Motivo" />
                        <x-text-input wire:model="motivo" id="mov_motivo" type="text" maxlength="255" required autocomplete="off"
                                      placeholder="{{ $tipoMovimiento === 'retiro' ? 'Pago a proveedor, depósito al banco…' : 'Cambio que se agrega al cajón…' }}" />
                        <x-input-error :messages="$errors->get('motivo')" class="mt-1.5" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                        <x-primary-button>Registrar</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('caja.corte')
            <x-modal nombre="cerrar-turno" titulo="Cerrar turno">
                <form wire:submit="cerrarTurno" wire:confirm="¿Cerrar el turno? Esta acción no se puede deshacer." class="space-y-5">
                    <p class="text-sm text-anil-800">Cuenta todo el efectivo del cajón (billetes y monedas) y escribe el total. Después de cerrar ya no se podrá vender en este turno.</p>
                    <div>
                        <x-input-label for="contado" value="Efectivo contado" />
                        <x-text-input wire:model="contado" id="contado" type="text" inputmode="decimal" class="font-mono text-2xl" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('contado')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="notas_cierre" value="Notas (opcional)" />
                        <x-text-input wire:model="notas" id="notas_cierre" type="text" maxlength="255" autocomplete="off" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Todavía no</x-secondary-button>
                        <x-primary-button>Cerrar turno</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</div>
