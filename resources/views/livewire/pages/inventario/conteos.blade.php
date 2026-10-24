<?php

use App\Actions\Inventario\GestionarConteo;
use App\Models\Conteo;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $nombre = '';

    public function mount(): void
    {
        $this->authorize('inventario.conteo');
        $this->nombre = 'Conteo del '.now()->translatedFormat('j \d\e F');
    }

    public function crear(GestionarConteo $gestor): void
    {
        $this->authorize('inventario.conteo');
        $this->resetValidation();

        try {
            $conteo = $gestor->crear($this->nombre, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('nombre', $e->errors()['nombre'][0]);

            return;
        }

        $this->redirectRoute('inventario.conteo', $conteo, navigate: true);
    }

    #[Computed]
    public function conteos()
    {
        return Conteo::query()->withCount('partidas')->with('usuario:id,name')->latest()->limit(30)->get();
    }
}; ?>

<div>
    <x-slot name="titulo">Conteo físico</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('inventario.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver al inventario
        </a>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <x-card titulo="Empezar un conteo" descripcion="Cuenta lo que hay en los estantes y compáralo con lo que dice el sistema. Puedes contar solo una parte de la tienda.">
            <form wire:submit="crear" class="space-y-4">
                <div>
                    <x-input-label for="nombre" value="Nombre del conteo" />
                    <x-text-input wire:model="nombre" id="nombre" type="text" maxlength="100" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
                </div>
                <x-primary-button class="w-full">Empezar a contar</x-primary-button>
            </form>
        </x-card>

        <x-card titulo="Conteos recientes" :padding="false">
            @if ($this->conteos->isEmpty())
                <div class="p-5"><x-empty icono="clipboard-text" titulo="Aún no hay conteos" texto="Empieza uno con el formulario." /></div>
            @else
                <ul class="divide-y divide-anil-50">
                    @foreach ($this->conteos as $c)
                        <li wire:key="c-{{ $c->id }}">
                            <a href="{{ route('inventario.conteo', $c) }}" wire:navigate class="flex min-h-16 items-center justify-between gap-3 px-5 py-3 hover:bg-anil-50/60">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold">{{ $c->nombre }}</span>
                                    <span class="text-xs text-anil-600">{{ $c->partidas_count }} {{ $c->partidas_count === 1 ? 'artículo' : 'artículos' }} · {{ $c->usuario?->name }} · {{ $c->created_at->format('d/m/Y H:i') }}</span>
                                </span>
                                <x-badge :tono="match ($c->estado) { 'aplicado' => 'ok', 'cancelado' => 'gris', default => 'aviso' }">
                                    {{ match ($c->estado) { 'aplicado' => 'Aplicado', 'cancelado' => 'Cancelado', default => 'En proceso' } }}
                                </x-badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
