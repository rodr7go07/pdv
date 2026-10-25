<?php

use App\Models\Caja;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    public string $serie = '';

    public string $ancho_papel = '80';

    public bool $imprimir_automatico = false;

    public bool $activa = true;

    public function mount(): void
    {
        $this->authorize('cajas.administrar');
    }

    #[Computed]
    public function lista()
    {
        return Caja::query()->withCount('ventas')
            ->addSelect(['turno_abierto' => \App\Models\Turno::query()->select('id')->whereColumn('caja_id', 'cajas.id')->where('estado', 'abierto')->limit(1)])
            ->orderBy('nombre')->get();
    }

    public function nueva(): void
    {
        $this->authorize('cajas.administrar');
        $this->reset('editandoId', 'nombre', 'serie', 'imprimir_automatico');
        $this->ancho_papel = '80';
        $this->activa = true;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'caja');
    }

    public function editar(int $id): void
    {
        $this->authorize('cajas.administrar');
        $c = Caja::query()->findOrFail($id);
        $this->fill([
            'editandoId' => $c->id, 'nombre' => $c->nombre, 'serie' => $c->serie, 'ancho_papel' => (string) $c->ancho_papel,
            'imprimir_automatico' => $c->imprimir_automatico, 'activa' => $c->activa,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'caja');
    }

    public function guardar(): void
    {
        $this->authorize('cajas.administrar');

        $this->serie = mb_strtoupper(trim($this->serie));

        $d = $this->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('cajas', 'nombre')->ignore($this->editandoId)],
            'serie' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('cajas', 'serie')->ignore($this->editandoId)],
            'ancho_papel' => ['required', Rule::in(['58', '80'])],
            'imprimir_automatico' => ['boolean'],
            'activa' => ['boolean'],
        ], [
            'nombre.unique' => 'Ya existe una caja con ese nombre.',
            'serie.unique' => 'Esa serie ya la usa otra caja.',
            'serie.regex' => 'La serie solo puede tener letras y números, sin espacios.',
        ], ['ancho_papel' => 'ancho del papel']);

        // La serie de una caja con ventas no se cambia: rompería la numeración de sus tickets.
        if ($this->editandoId) {
            $caja = Caja::query()->findOrFail($this->editandoId);

            if ($caja->serie !== $d['serie'] && $caja->ventas()->exists()) {
                $this->addError('serie', 'Esta caja ya tiene ventas: su serie no se puede cambiar.');

                return;
            }

            if (! $d['activa'] && \App\Models\Turno::query()->abiertos()->where('caja_id', $caja->id)->exists()) {
                $this->addError('activa', 'Esta caja tiene un turno abierto. Ciérralo antes de desactivarla.');

                return;
            }
        }

        Caja::query()->updateOrCreate(['id' => $this->editandoId], [
            'nombre' => trim($d['nombre']), 'serie' => $d['serie'], 'ancho_papel' => (int) $d['ancho_papel'],
            'imprimir_automatico' => $d['imprimir_automatico'], 'activa' => $d['activa'],
        ]);

        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'caja');
    }
}; ?>

<div>
    <x-slot name="titulo">Cajas</x-slot>

    <div class="max-w-3xl">
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="nueva"><x-phosphor-plus-bold class="size-4" /> Nueva caja</x-primary-button>
        </div>
        <x-card :padding="false">
            <ul class="divide-y divide-anil-50">
                @foreach ($this->lista as $c)
                    <li wire:key="cj-{{ $c->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p @class(['truncate font-semibold', 'text-anil-400 line-through' => ! $c->activa])>{{ $c->nombre }} <span class="font-mono text-sm font-normal text-anil-600">({{ $c->serie }})</span></p>
                            <p class="text-xs text-anil-600">Papel de {{ $c->ancho_papel }} mm · {{ $c->imprimir_automatico ? 'imprime solo' : 'impresión manual' }} · {{ number_format($c->ventas_count) }} {{ $c->ventas_count === 1 ? 'venta' : 'ventas' }}</p>
                        </div>
                        @if ($c->turno_abierto)<x-badge tono="aviso">Turno abierto</x-badge>@endif
                        <x-badge :tono="$c->activa ? 'ok' : 'gris'">{{ $c->activa ? 'Activa' : 'Inactiva' }}</x-badge>
                        <a href="{{ route('cajas.prueba', $c) }}" target="_blank" rel="noopener" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Imprimir prueba de {{ $c->nombre }}" title="Imprimir prueba"><x-phosphor-printer-bold class="size-5" /></a>
                        <button type="button" wire:click="editar({{ $c->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $c->nombre }}"><x-phosphor-pencil-simple-bold class="size-5" /></button>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    <x-modal nombre="caja" :titulo="$editandoId ? 'Editar caja' : 'Nueva caja'">
        <form wire:submit="guardar" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                <div>
                    <x-input-label for="caja_nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="caja_nombre" type="text" maxlength="60" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="caja_serie" value="Serie" />
                    <x-text-input wire:model="serie" id="caja_serie" type="text" maxlength="10" required class="font-mono uppercase" autocomplete="off" />
                    <x-input-error :messages="$errors->get('serie')" class="mt-1.5" />
                </div>
            </div>
            <p class="-mt-2 text-xs text-anil-600">La serie encabeza los folios de los tickets de esta caja (por ejemplo A-000123).</p>
            <div>
                <x-input-label for="caja_papel" value="Ancho del papel del ticket" />
                <x-select wire:model="ancho_papel" id="caja_papel">
                    <option value="58">58 mm (angosto)</option>
                    <option value="80">80 mm (estándar)</option>
                </x-select>
                <x-input-error :messages="$errors->get('ancho_papel')" class="mt-1.5" />
            </div>
            <x-checkbox wire:model="imprimir_automatico" label="Imprimir el ticket al cobrar" ayuda="Abre la impresión sin preguntar. Funciona mejor si el navegador tiene la impresora de tickets como predeterminada." />
            <div>
                <x-checkbox wire:model="activa" label="Caja activa" />
                <x-input-error :messages="$errors->get('activa')" class="mt-1.5" />
            </div>
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
