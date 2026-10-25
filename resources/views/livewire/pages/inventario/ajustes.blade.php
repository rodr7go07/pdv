<?php

use App\Actions\Inventario\AjustarInventario;
use App\Enums\MotivoAjuste;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\MovimientoInventario;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $articuloId = null;

    public string $direccion = 'salida';

    public string $cantidad = '';

    public string $motivo = 'merma';

    public string $nota = '';

    public ?string $exito = null;

    public function mount(): void
    {
        $this->authorize('inventario.ajustar');
    }

    #[On('articulo-elegido')]
    public function elegirArticulo(int $id): void
    {
        $this->articuloId = $id;
        $this->exito = null;
        $this->resetValidation();
    }

    public function cambiarArticulo(): void
    {
        $this->reset('articuloId', 'cantidad', 'nota', 'exito');
        $this->dispatch('enfocar-selector');
    }

    public function updatedMotivo(): void
    {
        // Merma, caducidad y robo solo pueden ser salidas.
        if (MotivoAjuste::from($this->motivo)->soloSalida()) {
            $this->direccion = 'salida';
        }
    }

    #[Computed]
    public function articulo(): ?Articulo
    {
        return $this->articuloId ? Articulo::query()->with('unidad')->find($this->articuloId) : null;
    }

    public function guardar(AjustarInventario $ajustar): void
    {
        $this->authorize('inventario.ajustar');
        abort_unless($this->articulo, 422);

        try {
            $movimiento = $ajustar($this->articulo, $this->direccion, trim($this->cantidad), MotivoAjuste::from($this->motivo), $this->nota, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn ($k) => $k === 'articulo' ? 'cantidad' : $k, array_keys($e->errors())),
                array_values($e->errors()),
            ));
        }

        $this->exito = 'Ajuste guardado: '.$this->articulo->descripcion.' ahora tiene '.rtrim(rtrim((string) $movimiento->existencia_resultante, '0'), '.').' '.$this->articulo->unidad->abreviatura.'.';
        $this->reset('articuloId', 'cantidad', 'nota');
        $this->motivo = 'merma';
        $this->direccion = 'salida';
        unset($this->articulo, $this->recientes);
        $this->dispatch('enfocar-selector');
    }

    #[Computed]
    public function recientes()
    {
        return MovimientoInventario::query()
            ->with(['articulo:id,clave,descripcion,unidad_medida_id', 'articulo.unidad:id,abreviatura', 'usuario:id,name'])
            ->where('tipo', TipoMovimiento::Ajuste->value)
            ->latest('created_at')->latest('id')
            ->limit(15)
            ->get();
    }
}; ?>

<div>
    <x-slot name="titulo">Ajustes de inventario</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('inventario.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver al inventario
        </a>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <x-card titulo="Nuevo ajuste" descripcion="Cambia la existencia con un motivo. Queda registrado en el kardex.">
            @if ($exito)
                <p class="mb-4 flex items-start gap-2 rounded-lg bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
                    <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ $exito }}
                </p>
            @endif

            @if (! $this->articulo)
                <livewire:articulos.selector origen="ajustes" :solo-inventariables="true" placeholder="Escanea o busca" />
            @else
                <div class="mb-5 flex items-start justify-between gap-3 rounded-lg bg-anil-50 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $this->articulo->descripcion }}</p>
                        <p class="font-mono text-xs text-anil-700">{{ $this->articulo->clave }}</p>
                        <p class="mt-1 text-sm">Existencia actual:
                            <strong class="font-mono">{{ rtrim(rtrim((string) $this->articulo->existencia, '0'), '.') ?: '0' }} {{ $this->articulo->unidad->abreviatura }}</strong>
                        </p>
                    </div>
                    <button type="button" wire:click="cambiarArticulo" class="shrink-0 text-sm font-semibold text-rosa-600 underline">Cambiar</button>
                </div>

                <form wire:submit="guardar" class="space-y-5">
                    <div>
                        <x-input-label for="motivo" value="Motivo" />
                        <x-select wire:model.live="motivo" id="motivo">
                            @foreach (MotivoAjuste::cases() as $m)
                                <option value="{{ $m->value }}">{{ $m->etiqueta() }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <fieldset>
                        <legend class="rotulo mb-1.5">Qué hacer</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label @class(['flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 text-sm font-semibold', 'border-rosa-500 bg-rosa-50 text-rosa-700' => $direccion === 'salida', 'border-anil-200 bg-white text-anil-800' => $direccion !== 'salida'])>
                                <input type="radio" wire:model.live="direccion" value="salida" class="sr-only"> <x-phosphor-minus-circle-bold class="size-5" /> Sacar
                            </label>
                            <label @class(['flex min-h-12 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-semibold', 'border-rosa-500 bg-rosa-50 text-rosa-700' => $direccion === 'entrada', 'border-anil-200 bg-white text-anil-800' => $direccion !== 'entrada', 'cursor-pointer' => ! MotivoAjuste::from($motivo)->soloSalida(), 'cursor-not-allowed opacity-40' => MotivoAjuste::from($motivo)->soloSalida()])>
                                <input type="radio" wire:model.live="direccion" value="entrada" class="sr-only" @disabled(MotivoAjuste::from($motivo)->soloSalida())> <x-phosphor-plus-circle-bold class="size-5" /> Meter
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('direccion')" class="mt-1.5" />
                    </fieldset>

                    <div>
                        <x-input-label for="cantidad" value="Cantidad ({{ $this->articulo->unidad->abreviatura }})" />
                        <x-text-input wire:model="cantidad" id="cantidad" type="text" inputmode="decimal" class="font-mono text-lg" required autofocus autocomplete="off" />
                        <x-input-error :messages="$errors->get('cantidad')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="nota" value="Nota {{ $motivo === 'otro' ? '(obligatoria)' : '(opcional)' }}" />
                        <x-text-input wire:model="nota" id="nota" type="text" maxlength="255" placeholder="Qué pasó" autocomplete="off" />
                        <x-input-error :messages="$errors->get('nota')" class="mt-1.5" />
                    </div>

                    <x-primary-button class="w-full" wire:loading.attr="disabled">Guardar ajuste</x-primary-button>
                </form>
            @endif
        </x-card>

        <x-card titulo="Últimos ajustes" :padding="false">
            @if ($this->recientes->isEmpty())
                <div class="p-5"><x-empty icono="sliders-horizontal" titulo="Todavía no hay ajustes" texto="Los ajustes que hagas aparecerán aquí." /></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($this->recientes as $m)
                        @php $c = (float) $m->cantidad; @endphp
                        <li wire:key="aj-{{ $m->id }}" class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $m->articulo->descripcion }}</p>
                                <p class="text-xs text-anil-600"><span class="capitalize">{{ $m->motivo }}</span> · {{ $m->usuario?->name }} · {{ $m->created_at->format('d/m/Y H:i') }}</p>
                                @if ($m->nota)<p class="text-xs text-anil-700">{{ $m->nota }}</p>@endif
                            </div>
                            <span @class(['shrink-0 font-mono font-semibold', 'text-emerald-700' => $c > 0, 'text-red-700' => $c < 0])>
                                {{ $c > 0 ? '+' : '' }}{{ rtrim(rtrim((string) $m->cantidad, '0'), '.') }} {{ $m->articulo->unidad->abreviatura }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
