<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email ?? '';
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ], attributes: ['name' => 'nombre', 'email' => 'correo']);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?: null,
        ])->save();

        $this->dispatch('profile-updated', name: $user->name);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-extrabold text-anil-900">Datos personales</h2>
        <p class="mt-1 text-sm text-anil-700">Tu usuario lo asigna el administrador y no se puede cambiar aquí.</p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-5">
        <div>
            <x-input-label for="usuario" value="Usuario" />
            <x-text-input id="usuario" type="text" :value="auth()->user()->usuario" disabled />
        </div>

        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input wire:model="name" id="name" type="text" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo (opcional)" />
            <x-text-input wire:model="email" id="email" type="email" autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar cambios</x-primary-button>
            <x-action-message on="profile-updated" />
        </div>
    </form>
</section>
