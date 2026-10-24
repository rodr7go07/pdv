<?php

use App\Actions\Usuarios\GuardarUsuario;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editandoId = null;

    public string $name = '';

    public string $usuario = '';

    public string $email = '';

    public string $password = '';

    public string $rol = 'Cajero';

    public bool $activo = true;

    public ?string $exito = null;

    public function mount(): void
    {
        $this->authorize('usuarios.administrar');
    }

    #[Computed]
    public function lista()
    {
        return User::query()->with('roles:id,name')->orderByDesc('activo')->orderBy('name')->get();
    }

    public function nuevo(): void
    {
        $this->authorize('usuarios.administrar');
        $this->reset('editandoId', 'name', 'usuario', 'email', 'password');
        $this->rol = 'Cajero';
        $this->activo = true;
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'usuario');
    }

    public function editar(int $id): void
    {
        $this->authorize('usuarios.administrar');
        $u = User::query()->with('roles:id,name')->findOrFail($id);
        $this->fill([
            'editandoId' => $u->id, 'name' => $u->name, 'usuario' => $u->usuario, 'email' => $u->email ?? '',
            'password' => '', 'rol' => $u->nombreRol() ?? 'Cajero', 'activo' => $u->activo,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'usuario');
    }

    public function guardar(GuardarUsuario $guardar): void
    {
        $this->resetValidation();

        try {
            $u = $guardar(
                $this->editandoId ? User::query()->findOrFail($this->editandoId) : null,
                ['name' => $this->name, 'usuario' => $this->usuario, 'email' => $this->email, 'password' => $this->password, 'rol' => $this->rol, 'activo' => $this->activo],
                auth()->user(),
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $this->exito = $this->editandoId ? "Se guardaron los cambios de {$u->name}." : "Usuario «{$u->usuario}» creado. Entrégale su usuario y su contraseña.";
        $this->reset('password');
        unset($this->lista);
        $this->dispatch('cerrar-modal', nombre: 'usuario');
    }

    /** @return array<string, list<string>> permisos legibles de cada rol */
    #[Computed]
    public function roles(): array
    {
        return collect(Rol::cases())->mapWithKeys(fn (Rol $r) => [$r->value => array_map(fn ($p) => $p->etiqueta(), $r->permisos())])->all();
    }
}; ?>

<div>
    <x-slot name="titulo">Usuarios</x-slot>
    <x-slot name="acciones">
        <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo usuario</x-primary-button>
    </x-slot>

    @if ($exito)
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status"><x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ $exito }}</p>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card :padding="false">
            <ul class="divide-y divide-anil-50">
                @foreach ($this->lista as $u)
                    <li wire:key="us-{{ $u->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-anil-100 font-display text-sm font-extrabold uppercase text-anil-800">{{ \Illuminate\Support\Str::of($u->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</span>
                        <div class="min-w-0 flex-1">
                            <p @class(['truncate font-semibold', 'text-anil-400 line-through' => ! $u->activo])>{{ $u->name }}</p>
                            <p class="truncate text-xs text-anil-600"><span class="font-mono">{{ $u->usuario }}</span>{{ $u->email ? ' · '.$u->email : '' }}</p>
                        </div>
                        <x-badge :tono="match ($u->nombreRol()) { 'Administrador' => 'rosa', 'Encargado' => 'aviso', default => 'gris' }">{{ $u->nombreRol() ?? 'Sin rol' }}</x-badge>
                        @unless ($u->activo)<x-badge tono="gris">Inactivo</x-badge>@endunless
                        <button type="button" wire:click="editar({{ $u->id }})" class="grid size-11 shrink-0 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar a {{ $u->name }}"><x-phosphor-pencil-simple-bold class="size-5" /></button>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card titulo="Qué puede hacer cada rol" descripcion="Elige el rol según lo que esa persona necesita.">
            <div class="space-y-5">
                @foreach ($this->roles as $nombre => $permisos)
                    <details class="group" @if ($loop->first) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-display text-base font-extrabold">
                            {{ $nombre }}
                            <x-phosphor-caret-down-bold class="size-4 text-anil-500 transition group-open:rotate-180" />
                        </summary>
                        <ul class="mt-2 space-y-1 text-sm text-anil-800">
                            @foreach ($permisos as $permiso)
                                <li class="flex items-start gap-2"><x-phosphor-check-bold class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $permiso }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </x-card>
    </div>

    <x-modal nombre="usuario" :titulo="$editandoId ? 'Editar usuario' : 'Nuevo usuario'" max-width="lg">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <x-input-label for="u_nombre" value="Nombre completo" />
                <x-text-input wire:model="name" id="u_nombre" type="text" maxlength="255" required autocomplete="off" />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="u_usuario" value="Usuario para entrar" />
                    <x-text-input wire:model="usuario" id="u_usuario" type="text" maxlength="40" required class="font-mono" autocomplete="off" autocapitalize="none" spellcheck="false" />
                    <x-input-error :messages="$errors->get('usuario')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="u_correo" value="Correo (opcional)" />
                    <x-text-input wire:model="email" id="u_correo" type="email" maxlength="255" autocomplete="off" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="u_rol" value="Rol" />
                    <x-select wire:model="rol" id="u_rol">
                        @foreach (App\Enums\Rol::cases() as $r)<option value="{{ $r->value }}">{{ $r->value }}</option>@endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('rol')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="u_clave" :value="$editandoId ? 'Contraseña nueva (opcional)' : 'Contraseña'" />
                    <x-text-input wire:model="password" id="u_clave" type="text" autocomplete="new-password" :required="! $editandoId" placeholder="Mínimo 8 caracteres" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>
            </div>
            @if ($editandoId)<p class="-mt-2 text-xs text-anil-600">Si escribes una contraseña nueva, la anterior deja de servir. Déjala vacía para no cambiarla.</p>@endif
            <div>
                <x-checkbox wire:model="activo" label="Usuario activo" ayuda="Si lo desactivas ya no puede entrar, pero se conserva todo lo que hizo." />
                <x-input-error :messages="$errors->get('activo')" class="mt-1.5" />
            </div>
            <div class="flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
