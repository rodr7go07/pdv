<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::min(8), 'confirmed'],
            ], attributes: [
                'current_password' => 'contraseña actual',
                'password' => 'contraseña nueva',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-extrabold text-anil-900">Cambiar contraseña</h2>
        <p class="mt-1 text-sm text-anil-700">Usa al menos 8 caracteres. No la compartas con otros cajeros.</p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-5">
        <div>
            <x-input-label for="current_password" value="Contraseña actual" />
            <x-text-input wire:model="current_password" id="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Contraseña nueva" />
            <x-text-input wire:model="password" id="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Repite la contraseña nueva" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Actualizar contraseña</x-primary-button>
            <x-action-message on="password-updated" />
        </div>
    </form>
</section>
