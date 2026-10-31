<?php

use App\Actions\Usuarios\EliminarRol;
use App\Actions\Usuarios\GuardarRol;
use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editandoId = null;

    public string $nombre = '';

    /** @var list<string> permisos marcados (valores de App\Enums\Permiso) */
    public array $permisos = [];

    public ?int $borrandoId = null;

    public ?string $exito = null;

    public function mount(): void
    {
        $this->authorize('roles.administrar');
    }

    /** Roles base primero (Administrador, Encargado, Cajero) y luego los de la tienda por nombre. */
    #[Computed]
    public function lista()
    {
        return Role::query()->withCount(['permissions', 'users'])->get()
            ->sortBy(fn (Role $r) => [Rol::orden($r->name), mb_strtolower($r->name)])
            ->values();
    }

    /** @return array<string, list<Permiso>> permisos agrupados por módulo */
    #[Computed]
    public function grupos(): array
    {
        $orden = ['Ventas', 'Caja', 'Artículos', 'Inventario', 'Compras y proveedores', 'Clientes y crédito', 'Reportes', 'Administración'];

        return collect(Permiso::cases())->groupBy(fn (Permiso $p) => $p->grupo())->map(fn ($g) => $g->all())
            ->sortBy(fn ($g, string $grupo) => array_search($grupo, $orden, true))->all();
    }

    public function nuevo(): void
    {
        $this->authorize('roles.administrar');
        $this->reset('editandoId', 'nombre', 'permisos');
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'rol');
    }

    public function editar(int $id): void
    {
        $this->authorize('roles.administrar');
        $rol = Role::query()->with('permissions')->findOrFail($id);

        if ($rol->name === Rol::Administrador->value) {
            return; // siempre tiene todos los permisos
        }

        $this->fill(['editandoId' => $rol->id, 'nombre' => $rol->name, 'permisos' => $rol->permissions->pluck('name')->all()]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'rol');
    }

    public function marcarTodo(): void
    {
        $this->permisos = array_map(fn (Permiso $p) => $p->value, array_filter(Permiso::cases(), fn (Permiso $p) => auth()->user()->can($p->value)));
    }

    public function quitarTodo(): void
    {
        $this->permisos = [];
    }

    public function guardar(GuardarRol $guardar): void
    {
        $this->resetValidation();

        try {
            $rol = $guardar($this->editandoId ? Role::query()->findOrFail($this->editandoId) : null, $this->nombre, $this->permisos, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->exito = ($this->editandoId ? "Se guardaron los cambios del rol «{$rol->name}»." : "Rol «{$rol->name}» creado.").' Los cambios aplican de inmediato a quienes lo tienen.';
        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'rol');
    }

    /** Un rol base vuelve a los permisos con los que nació. */
    public function restablecer(GuardarRol $guardar): void
    {
        $this->authorize('roles.administrar');
        $rol = Role::query()->findOrFail((int) $this->editandoId);
        abort_unless(Rol::esBase($rol->name), 404);

        $this->permisos = array_map(fn (Permiso $p) => $p->value, Rol::from($rol->name)->permisos());
    }

    public function confirmarBorrado(int $id): void
    {
        $this->authorize('roles.administrar');
        $this->borrandoId = $id;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'borrar-rol');
    }

    public function borrar(EliminarRol $eliminar): void
    {
        $this->resetValidation();

        try {
            $rol = Role::query()->findOrFail((int) $this->borrandoId);
            $nombre = $rol->name;
            $eliminar($rol, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->exito = "Rol «{$nombre}» borrado.";
        $this->borrandoId = null;
        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'borrar-rol');
    }
}; ?>

<div>
    <x-slot name="titulo">Roles y permisos</x-slot>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-anil-700">Un rol es un conjunto de permisos. Crea los que necesites (por ejemplo «Almacenista») y asígnaselos a cada persona en <a href="{{ route('usuarios') }}" wire:navigate class="font-semibold text-rosa-600 underline">Usuarios</a>.</p>
        <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo rol</x-primary-button>
    </div>

    @if ($exito)
        <x-alerta class="mb-5">{{ $exito }}</x-alerta>
    @endif

    <x-card :padding="false" class="max-w-4xl">
        <ul class="divide-y divide-anil-50">
            @foreach ($this->lista as $r)
                @php $base = \App\Enums\Rol::esBase($r->name); $admin = $r->name === \App\Enums\Rol::Administrador->value; @endphp
                <li wire:key="rol-{{ $r->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold">
                            <span class="truncate">{{ $r->name }}</span>
                            @if ($base)<x-badge tono="gris">Del sistema</x-badge>@endif
                        </p>
                        <p class="text-xs text-anil-600">
                            {{ $admin ? 'Todos los permisos, siempre' : $r->permissions_count.' '.($r->permissions_count === 1 ? 'permiso' : 'permisos') }}
                            · {{ $r->users_count }} {{ $r->users_count === 1 ? 'usuario' : 'usuarios' }}
                        </p>
                    </div>
                    @unless ($admin)
                        <button type="button" wire:click="editar({{ $r->id }})" class="grid size-11 shrink-0 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar el rol {{ $r->name }}"><x-phosphor-pencil-simple-bold class="size-5" /></button>
                    @endunless
                    @unless ($base)
                        <button type="button" wire:click="confirmarBorrado({{ $r->id }})" class="grid size-11 shrink-0 place-items-center rounded-lg text-anil-700 hover:bg-red-50 hover:text-red-700" aria-label="Borrar el rol {{ $r->name }}"><x-phosphor-trash-bold class="size-5" /></button>
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal nombre="rol" :titulo="$editandoId ? 'Editar rol' : 'Nuevo rol'" max-width="xl">
        <form wire:submit="guardar" class="space-y-5">
            @php $esBaseEditando = $editandoId && \App\Enums\Rol::esBase($nombre); @endphp
            <div>
                <x-input-label for="rol_nombre" value="Nombre del rol" />
                <x-text-input wire:model="nombre" id="rol_nombre" type="text" maxlength="40" required autocomplete="off" :readonly="$esBaseEditando" />
                @if ($esBaseEditando)<p class="mt-1.5 text-xs text-anil-600">Este rol viene con el sistema: se pueden cambiar sus permisos pero no su nombre.</p>@endif
                <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
                <x-input-error :messages="$errors->get('rol')" class="mt-1.5" />
            </div>

            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="rotulo">Qué puede hacer ({{ count($permisos) }} {{ count($permisos) === 1 ? 'permiso' : 'permisos' }})</p>
                    <div class="flex flex-wrap gap-3 text-sm font-semibold">
                        <button type="button" wire:click="marcarTodo" class="min-h-9 text-rosa-600 underline">Marcar todo</button>
                        <button type="button" wire:click="quitarTodo" class="min-h-9 text-rosa-600 underline">Quitar todo</button>
                        @if ($esBaseEditando)<button type="button" wire:click="restablecer" class="min-h-9 text-rosa-600 underline">Permisos de fábrica</button>@endif
                    </div>
                </div>
                <x-input-error :messages="$errors->get('permisos')" class="mb-2" />
                <div class="max-h-[50dvh] space-y-4 overflow-y-auto rounded-lg border border-anil-100 p-3 sm:p-4">
                    @foreach ($this->grupos as $grupo => $items)
                        <fieldset>
                            <legend class="rotulo mb-1.5">{{ $grupo }}</legend>
                            <div class="grid grid-cols-[minmax(0,1fr)] gap-x-4 sm:grid-cols-2">
                                @foreach ($items as $p)
                                    <label class="flex min-h-11 cursor-pointer items-start gap-2.5 py-2 text-sm">
                                        <input type="checkbox" wire:model.live="permisos" value="{{ $p->value }}" class="mt-0.5 size-5 shrink-0 rounded border-anil-300 text-rosa-600 focus:ring-rosa-500/30">
                                        <span class="min-w-0">{{ $p->etiqueta() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>

    <x-modal nombre="borrar-rol" titulo="Borrar rol">
        <div class="space-y-4">
            <p class="text-sm text-anil-800">¿Borrar el rol <strong>{{ $this->lista->firstWhere('id', $borrandoId)?->name }}</strong>? Solo se puede si ya nadie lo tiene.</p>
            <x-input-error :messages="$errors->get('rol')" />
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">No borrar</x-secondary-button>
                <x-primary-button type="button" wire:click="borrar">Borrar rol</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
