<?php

use App\Enums\TipoImpuesto;
use App\Models\Impuesto;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    public string $tipo = 'iva';

    public string $tasa = '0';

    public bool $exento = false;

    public bool $activo = true;

    public function mount(): void
    {
        $this->authorize('catalogos.administrar');
    }

    #[Computed]
    public function lista()
    {
        return Impuesto::query()->withCount('articulos')->orderBy('tipo')->orderBy('nombre')->get();
    }

    public function nuevo(): void
    {
        $this->authorize('catalogos.administrar');
        $this->reset('editandoId', 'nombre', 'exento');
        $this->fill(['tipo' => 'iva', 'tasa' => '0', 'activo' => true]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'impuesto');
    }

    public function editar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $i = Impuesto::query()->findOrFail($id);
        $this->fill([
            'editandoId' => $i->id, 'nombre' => $i->nombre, 'tipo' => $i->tipo->value,
            'tasa' => \App\Support\Numero::cantidad($i->tasa), 'exento' => $i->exento, 'activo' => $i->activo,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'impuesto');
    }

    public function guardar(): void
    {
        $this->authorize('catalogos.administrar');

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('impuestos', 'nombre')->ignore($this->editandoId)],
            'tipo' => ['required', Rule::enum(TipoImpuesto::class)],
            'tasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'exento' => ['boolean'],
            'activo' => ['boolean'],
        ], ['nombre.unique' => 'Ya existe un impuesto con ese nombre.'], ['tasa' => 'tasa']);

        Impuesto::query()->updateOrCreate(['id' => $this->editandoId], [
            'nombre' => trim($datos['nombre']),
            'tipo' => $datos['tipo'],
            'tasa' => $datos['exento'] ? 0 : $datos['tasa'],
            'exento' => $datos['exento'],
            'activo' => $datos['activo'],
        ]);

        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'impuesto');
    }

    public function alternar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $i = Impuesto::query()->findOrFail($id);
        $i->update(['activo' => ! $i->activo]);
        unset($this->lista);
    }
}; ?>

<div>
    <x-card titulo="Impuestos" descripcion="IVA e IEPS que se pueden asignar a los artículos." :padding="false">
        <x-slot name="acciones">
            <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo</x-primary-button>
        </x-slot>

        <ul class="divide-y divide-anil-50">
            @foreach ($this->lista as $i)
                <li wire:key="i-{{ $i->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p @class(['font-semibold', 'text-anil-400 line-through' => ! $i->activo])>{{ $i->nombre }}</p>
                        <p class="text-xs text-anil-600">
                            {{ $i->tipo->etiqueta() }} · {{ $i->exento ? 'Exento' : \App\Support\Numero::cantidad($i->tasa).'%' }} ·
                            {{ $i->articulos_count }} {{ $i->articulos_count === 1 ? 'artículo' : 'artículos' }}
                        </p>
                    </div>
                    <x-badge :tono="$i->activo ? 'ok' : 'gris'">{{ $i->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                    <button type="button" wire:click="editar({{ $i->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $i->nombre }}">
                        <x-phosphor-pencil-simple-bold class="size-5" />
                    </button>
                    <button type="button" wire:click="alternar({{ $i->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50"
                            aria-label="{{ $i->activo ? 'Desactivar' : 'Activar' }} {{ $i->nombre }}" title="{{ $i->activo ? 'Desactivar' : 'Activar' }}">
                        @if ($i->activo)
                            <x-phosphor-eye-slash-bold class="size-5" />
                        @else
                            <x-phosphor-eye-bold class="size-5" />
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal nombre="impuesto" :titulo="$editandoId ? 'Editar impuesto' : 'Nuevo impuesto'">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <x-input-label for="i_nombre" value="Nombre" />
                <x-text-input wire:model="nombre" id="i_nombre" type="text" maxlength="60" required autocomplete="off" placeholder="IVA 16%" />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="i_tipo" value="Tipo" />
                    <x-select wire:model="tipo" id="i_tipo">
                        @foreach (App\Enums\TipoImpuesto::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->etiqueta() }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('tipo')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="i_tasa" value="Tasa (%)" />
                    <x-text-input wire:model="tasa" id="i_tasa" type="text" inputmode="decimal" class="font-mono" required />
                    <x-input-error :messages="$errors->get('tasa')" class="mt-1.5" />
                </div>
            </div>
            <x-checkbox wire:model="exento" label="Exento" ayuda="No cobra impuesto, aunque se muestre en el ticket." />
            <x-checkbox wire:model="activo" label="Activo" />
            @if ($editandoId)
                <p class="rounded-lg bg-etiqueta/25 px-3 py-2.5 text-sm text-amber-900">
                    Cambiar la tasa afecta los márgenes y los cobros nuevos. Las ventas ya hechas no cambian.
                </p>
            @endif
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
