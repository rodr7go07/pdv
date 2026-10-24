<?php

use App\Actions\Caja\CerrarTurno;
use App\Models\Turno;
use App\Services\ResumenTurno;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Turno $turno;

    public string $contado = '';

    public string $notas = '';

    public ?string $aviso = null;

    public function mount(Turno $turno): void
    {
        $u = auth()->user();
        abort_unless($u->can('caja.supervisar') || $turno->user_id === $u->id, 403);

        $this->turno = $turno->load(['caja', 'usuario:id,name', 'cerradoPor:id,name']);
    }

    public function resumen(): array
    {
        return $this->turno->cortes()->where('tipo', 'Z')->first()?->datos ?? ResumenTurno::calcular($this->turno);
    }

    public function corteX(CerrarTurno $cerrar): void
    {
        $cerrar->corteX($this->turno, auth()->user());
        $this->aviso = 'Corte parcial generado.';
    }

    public function cerrar(CerrarTurno $cerrar): void
    {
        $this->resetValidation();

        try {
            $cerrar->cerrar($this->turno, trim($this->contado), auth()->user(), $this->notas);
        } catch (ValidationException $e) {
            $this->addError('contado', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->turno->refresh()->load(['caja', 'usuario:id,name', 'cerradoPor:id,name']);
        $this->reset('contado', 'notas');
        $this->aviso = 'Turno cerrado.';
        $this->dispatch('cerrar-modal', nombre: 'cerrar-turno');
    }
}; ?>

<div>
    <x-slot name="titulo">Turno de {{ $turno->usuario->name }}</x-slot>
    <x-slot name="acciones">
        @can('caja.supervisar')
            <a href="{{ route('caja.turnos') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
                <x-phosphor-arrow-left-bold class="size-4" /> Todos los turnos
            </a>
        @endcan
    </x-slot>

    @if ($aviso)
        <p class="mb-5 flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status"><x-phosphor-check-circle-bold class="size-5 shrink-0" /> {{ $aviso }}</p>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-anil-100 bg-white px-5 py-4 shadow-[0_2px_0_0_var(--color-anil-100)]">
        <div>
            <p class="font-display text-xl font-extrabold">{{ $turno->caja->nombre }}</p>
            <p class="text-sm text-anil-700">
                Abierto el {{ $turno->abierto_at->translatedFormat('j \d\e F, H:i') }}
                @if ($turno->cerrado_at) · cerrado el {{ $turno->cerrado_at->translatedFormat('j \d\e F, H:i') }} por {{ $turno->cerradoPor?->name }} @endif
            </p>
        </div>
        <x-badge :tono="$turno->estaAbierto() ? 'ok' : 'gris'">{{ $turno->estaAbierto() ? 'Turno abierto' : 'Turno cerrado' }}</x-badge>
    </div>

    <x-resumen-turno :resumen="$this->resumen()" :contado="$turno->efectivo_contado" :diferencia="$turno->diferencia" />

    @if ($turno->notas)
        <p class="mt-4 text-sm text-anil-800"><strong>Notas del cierre:</strong> {{ $turno->notas }}</p>
    @endif

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
        <x-card titulo="Cortes" :padding="false">
            @php $cortes = $turno->cortes()->get(); @endphp
            @if ($cortes->isEmpty())
                <div class="p-5"><p class="text-sm text-anil-700">Este turno todavía no tiene cortes.</p></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($cortes as $c)
                        <li>
                            <a href="{{ route('caja.corte', $c) }}" target="_blank" rel="noopener" class="flex min-h-14 items-center justify-between gap-3 px-5 py-3 hover:bg-anil-50/60">
                                <span><span class="block font-semibold">{{ $c->etiqueta() }}</span><span class="text-xs text-anil-600">{{ $c->created_at->format('d/m/Y H:i') }}</span></span>
                                <x-phosphor-printer-bold class="size-5 text-anil-500" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card titulo="Entradas y retiros" :padding="false">
            @php $movs = $turno->movimientos()->with('usuario:id,name')->get(); @endphp
            @if ($movs->isEmpty())
                <div class="p-5"><p class="text-sm text-anil-700">No hubo entradas ni retiros.</p></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($movs as $m)
                        <li class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0"><p class="font-semibold">{{ $m->tipo === 'retiro' ? 'Retiro' : 'Entrada' }}</p><p class="text-xs text-anil-600">{{ $m->motivo }} · {{ $m->usuario?->name }} · {{ $m->created_at->format('H:i') }}</p></div>
                            <p @class(['shrink-0 font-mono font-semibold', 'text-red-700' => $m->tipo === 'retiro', 'text-emerald-700' => $m->tipo === 'entrada'])>{{ $m->tipo === 'retiro' ? '−' : '+' }}${{ number_format((float) $m->monto, 2) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    @if ($turno->estaAbierto())
        @can('caja.corte')
            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" wire:click="corteX" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-anil-200 bg-white px-4 text-sm font-semibold text-anil-800 hover:bg-anil-50"><x-phosphor-file-text-bold class="size-5" /> Corte parcial (X)</button>
                <button type="button" x-on:click="$dispatch('abrir-modal', { nombre: 'cerrar-turno' })" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-anil-900 px-4 text-sm font-semibold text-white hover:bg-anil-800"><x-phosphor-lock-key-bold class="size-5" /> Cerrar este turno</button>
            </div>

            <x-modal nombre="cerrar-turno" titulo="Cerrar turno">
                <form wire:submit="cerrar" wire:confirm="¿Cerrar el turno? Esta acción no se puede deshacer." class="space-y-5">
                    <p class="text-sm text-anil-800">Escribe el efectivo que se contó en el cajón.</p>
                    <div>
                        <x-input-label for="contado_t" value="Efectivo contado" />
                        <x-text-input wire:model="contado" id="contado_t" type="text" inputmode="decimal" class="font-mono text-2xl" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('contado')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="notas_t" value="Notas (opcional)" />
                        <x-text-input wire:model="notas" id="notas_t" type="text" maxlength="255" autocomplete="off" />
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
