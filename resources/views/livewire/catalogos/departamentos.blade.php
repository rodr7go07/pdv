<?php

use App\Models\Departamento;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    public bool $activo = true;

    public function mount(): void
    {
        $this->authorize('catalogos.administrar');
    }

    #[Computed]
    public function lista()
    {
        return Departamento::query()->withCount('articulos')->orderBy('nombre')->get();
    }

    public function nuevo(): void
    {
        $this->authorize('catalogos.administrar');
        $this->reset('editandoId', 'nombre');
        $this->activo = true;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'departamento');
    }

    public function editar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $d = Departamento::query()->findOrFail($id);
        $this->editandoId = $d->id;
        $this->nombre = $d->nombre;
        $this->activo = $d->activo;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'departamento');
    }

    public function guardar(): void
    {
        $this->authorize('catalogos.administrar');

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('departamentos', 'nombre')->ignore($this->editandoId)],
            'activo' => ['boolean'],
        ], [
            'nombre.unique' => 'Ya existe un departamento con ese nombre (quizá desactivado).',
        ]);

        Departamento::query()->updateOrCreate(['id' => $this->editandoId], ['nombre' => trim($datos['nombre']), 'activo' => $datos['activo']]);

        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'departamento');
    }

    public function alternar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $d = Departamento::query()->findOrFail($id);
        $d->update(['activo' => ! $d->activo]);
        unset($this->lista);
    }
}; ?>

<div>
    <x-card titulo="Departamentos" descripcion="Agrupan los artículos: abarrotes, lácteos, bebidas…" :padding="false">
        <x-slot name="acciones">
            <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo</x-primary-button>
        </x-slot>

        @if ($this->lista->isEmpty())
            <div class="p-5"><x-empty icono="folders" titulo="Sin departamentos" texto="Crea al menos uno para poder dar de alta artículos." /></div>
        @else
            <ul class="divide-y divide-anil-50">
                @foreach ($this->lista as $d)
                    <li wire:key="d-{{ $d->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p @class(['font-semibold', 'text-anil-400 line-through' => ! $d->activo])>{{ $d->nombre }}</p>
                            <p class="text-xs text-anil-600">{{ $d->articulos_count }} {{ $d->articulos_count === 1 ? 'artículo' : 'artículos' }}</p>
                        </div>
                        <x-badge :tono="$d->activo ? 'ok' : 'gris'">{{ $d->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                        <button type="button" wire:click="editar({{ $d->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $d->nombre }}">
                            <x-phosphor-pencil-simple-bold class="size-5" />
                        </button>
                        <button type="button" wire:click="alternar({{ $d->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50"
                                aria-label="{{ $d->activo ? 'Desactivar' : 'Activar' }} {{ $d->nombre }}" title="{{ $d->activo ? 'Desactivar' : 'Activar' }}">
                            @if ($d->activo)
                                <x-phosphor-eye-slash-bold class="size-5" />
                            @else
                                <x-phosphor-eye-bold class="size-5" />
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-modal nombre="departamento" :titulo="$editandoId ? 'Editar departamento' : 'Nuevo departamento'">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <x-input-label for="depto_nombre" value="Nombre" />
                <x-text-input wire:model="nombre" id="depto_nombre" type="text" maxlength="80" required autocomplete="off" />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
            </div>
            <x-checkbox wire:model="activo" label="Activo" />
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
