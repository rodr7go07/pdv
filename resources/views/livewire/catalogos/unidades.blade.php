<?php

use App\Models\Articulo;
use App\Models\UnidadMedida;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    public string $abreviatura = '';

    public bool $permite_decimales = false;

    public bool $activo = true;

    public function mount(): void
    {
        $this->authorize('catalogos.administrar');
    }

    #[Computed]
    public function lista()
    {
        return UnidadMedida::query()->withCount('articulos')->orderBy('nombre')->get();
    }

    public function nuevo(): void
    {
        $this->authorize('catalogos.administrar');
        $this->reset('editandoId', 'nombre', 'abreviatura', 'permite_decimales');
        $this->activo = true;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'unidad');
    }

    public function editar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $u = UnidadMedida::query()->findOrFail($id);
        $this->fill([
            'editandoId' => $u->id, 'nombre' => $u->nombre, 'abreviatura' => $u->abreviatura,
            'permite_decimales' => $u->permite_decimales, 'activo' => $u->activo,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'unidad');
    }

    public function guardar(): void
    {
        $this->authorize('catalogos.administrar');

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:40', Rule::unique('unidades_medida', 'nombre')->ignore($this->editandoId)],
            'abreviatura' => ['required', 'string', 'max:10', Rule::unique('unidades_medida', 'abreviatura')->ignore($this->editandoId)],
            'permite_decimales' => ['boolean'],
            'activo' => ['boolean'],
        ], [
            'nombre.unique' => 'Ya existe una unidad con ese nombre.',
            'abreviatura.unique' => 'Ya existe una unidad con esa abreviatura.',
        ]);

        if ($this->editandoId && ! $datos['permite_decimales']
            && Articulo::query()->where('unidad_medida_id', $this->editandoId)->where('granel', true)->exists()) {
            throw ValidationException::withMessages([
                'permite_decimales' => 'Hay artículos a granel con esta unidad. Cámbialos de unidad antes de quitar los decimales.',
            ]);
        }

        UnidadMedida::query()->updateOrCreate(['id' => $this->editandoId], [
            'nombre' => trim($datos['nombre']),
            'abreviatura' => trim($datos['abreviatura']),
            'permite_decimales' => $datos['permite_decimales'],
            'activo' => $datos['activo'],
        ]);

        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'unidad');
    }

    public function alternar(int $id): void
    {
        $this->authorize('catalogos.administrar');
        $u = UnidadMedida::query()->findOrFail($id);
        $u->update(['activo' => ! $u->activo]);
        unset($this->lista);
    }
}; ?>

<div>
    <x-card titulo="Unidades de medida" descripcion="Cómo se cuenta cada artículo: pieza, kilo, litro, caja…" :padding="false">
        <x-slot name="acciones">
            <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nueva</x-primary-button>
        </x-slot>

        <ul class="divide-y divide-anil-50">
            @foreach ($this->lista as $u)
                <li wire:key="u-{{ $u->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p @class(['font-semibold', 'text-anil-400 line-through' => ! $u->activo])>
                            {{ $u->nombre }} <span class="font-mono text-sm font-normal text-anil-600">({{ $u->abreviatura }})</span>
                        </p>
                        <p class="text-xs text-anil-600">
                            {{ $u->permite_decimales ? 'Permite decimales' : 'Solo enteros' }} · {{ $u->articulos_count }} {{ $u->articulos_count === 1 ? 'artículo' : 'artículos' }}
                        </p>
                    </div>
                    <x-badge :tono="$u->activo ? 'ok' : 'gris'">{{ $u->activo ? 'Activa' : 'Inactiva' }}</x-badge>
                    <button type="button" wire:click="editar({{ $u->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $u->nombre }}">
                        <x-phosphor-pencil-simple-bold class="size-5" />
                    </button>
                    <button type="button" wire:click="alternar({{ $u->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50"
                            aria-label="{{ $u->activo ? 'Desactivar' : 'Activar' }} {{ $u->nombre }}" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                        @if ($u->activo)
                            <x-phosphor-eye-slash-bold class="size-5" />
                        @else
                            <x-phosphor-eye-bold class="size-5" />
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal nombre="unidad" :titulo="$editandoId ? 'Editar unidad' : 'Nueva unidad'">
        <form wire:submit="guardar" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                <div>
                    <x-input-label for="u_nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="u_nombre" type="text" maxlength="40" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="u_abrev" value="Abreviatura" />
                    <x-text-input wire:model="abreviatura" id="u_abrev" type="text" maxlength="10" required autocomplete="off" class="font-mono" />
                    <x-input-error :messages="$errors->get('abreviatura')" class="mt-1.5" />
                </div>
            </div>
            <div>
                <x-checkbox wire:model="permite_decimales" label="Permite decimales" ayuda="Necesario para vender a granel (por ejemplo 0.250 kg)." />
                <x-input-error :messages="$errors->get('permite_decimales')" class="mt-1.5" />
            </div>
            <x-checkbox wire:model="activo" label="Activa" />
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
