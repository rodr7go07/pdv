<?php

use App\Actions\Proveedores\GuardarProveedor;
use App\Models\Proveedor;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    public string $contacto = '';

    public string $telefono = '';

    public string $correo = '';

    public string $rfc = '';

    public string $notas = '';

    public bool $activo = true;

    public string $buscar = '';

    public function mount(): void
    {
        $this->authorize('proveedores.administrar');
    }

    #[Computed]
    public function lista()
    {
        return Proveedor::query()->withCount('compras')
            ->when($this->buscar !== '', fn ($q) => $q->where('nombre', 'like', '%'.addcslashes($this->buscar, '%_\\').'%'))
            ->orderBy('nombre')->get();
    }

    public function nuevo(): void
    {
        $this->authorize('proveedores.administrar');
        $this->reset('editandoId', 'nombre', 'contacto', 'telefono', 'correo', 'rfc', 'notas');
        $this->activo = true;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'proveedor');
    }

    public function editar(int $id): void
    {
        $this->authorize('proveedores.administrar');
        $p = Proveedor::query()->findOrFail($id);
        $this->fill([
            'editandoId' => $p->id, 'nombre' => $p->nombre, 'contacto' => $p->contacto ?? '', 'telefono' => $p->telefono ?? '',
            'correo' => $p->correo ?? '', 'rfc' => $p->rfc ?? '', 'notas' => $p->notas ?? '', 'activo' => $p->activo,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'proveedor');
    }

    public function guardar(GuardarProveedor $guardar): void
    {
        $this->resetValidation();

        try {
            $guardar(
                $this->editandoId ? Proveedor::query()->findOrFail($this->editandoId) : null,
                $this->only('nombre', 'contacto', 'telefono', 'correo', 'rfc', 'notas', 'activo'),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'proveedor');
    }

    public function alternar(int $id): void
    {
        $this->authorize('proveedores.administrar');
        $p = Proveedor::query()->findOrFail($id);
        $p->update(['activo' => ! $p->activo]);
        unset($this->lista);
    }
}; ?>

<div>
    <x-slot name="titulo">Proveedores</x-slot>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-enlace-volver :href="route('compras.index')">Volver a compras</x-enlace-volver>
        <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo</x-primary-button>
    </div>

    <div class="max-w-3xl">
        <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Buscar proveedor" aria-label="Buscar proveedor" autocomplete="off"
               class="mb-4 block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">

        @if ($this->lista->isEmpty())
            <x-empty icono="truck" titulo="No hay proveedores" texto="Da de alta a quienes te surten para poder registrar compras." />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-anil-50">
                    @foreach ($this->lista as $p)
                        <li wire:key="pr-{{ $p->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <div class="min-w-0 flex-1">
                                <p @class(['truncate font-semibold', 'text-anil-400 line-through' => ! $p->activo])>{{ $p->nombre }}</p>
                                <p class="truncate text-xs text-anil-600">
                                    {{ collect([$p->contacto, $p->telefono, $p->correo])->filter()->implode(' · ') ?: 'Sin datos de contacto' }}
                                    · {{ $p->compras_count }} {{ $p->compras_count === 1 ? 'compra' : 'compras' }}
                                </p>
                            </div>
                            <x-badge :tono="$p->activo ? 'ok' : 'gris'">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                            <button type="button" wire:click="editar({{ $p->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $p->nombre }}">
                                <x-phosphor-pencil-simple-bold class="size-5" />
                            </button>
                            <button type="button" wire:click="alternar({{ $p->id }})" class="grid size-11 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="{{ $p->activo ? 'Desactivar' : 'Activar' }} {{ $p->nombre }}">
                                @if ($p->activo)<x-phosphor-eye-slash-bold class="size-5" />@else<x-phosphor-eye-bold class="size-5" />@endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    <x-modal nombre="proveedor" :titulo="$editandoId ? 'Editar proveedor' : 'Nuevo proveedor'" max-width="lg">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <x-input-label for="pv_nombre" value="Nombre o razón social" />
                <x-text-input wire:model="nombre" id="pv_nombre" type="text" maxlength="120" required autocomplete="off" />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="pv_contacto" value="Contacto" />
                    <x-text-input wire:model="contacto" id="pv_contacto" type="text" maxlength="120" autocomplete="off" />
                </div>
                <div>
                    <x-input-label for="pv_tel" value="Teléfono" />
                    <x-text-input wire:model="telefono" id="pv_tel" type="tel" maxlength="20" inputmode="tel" autocomplete="off" />
                    <x-input-error :messages="$errors->get('telefono')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="pv_correo" value="Correo" />
                    <x-text-input wire:model="correo" id="pv_correo" type="email" maxlength="120" autocomplete="off" />
                    <x-input-error :messages="$errors->get('correo')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="pv_rfc" value="RFC" />
                    <x-text-input wire:model="rfc" id="pv_rfc" type="text" maxlength="13" class="font-mono uppercase" autocomplete="off" />
                    <x-input-error :messages="$errors->get('rfc')" class="mt-1.5" />
                </div>
            </div>
            <div>
                <x-input-label for="pv_notas" value="Notas" />
                <x-text-input wire:model="notas" id="pv_notas" type="text" maxlength="2000" placeholder="Días de visita, condiciones de pago…" autocomplete="off" />
            </div>
            <x-checkbox wire:model="activo" label="Activo" />
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
