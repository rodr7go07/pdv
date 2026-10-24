<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<form wire:submit="login" class="space-y-5">
    <div>
        <x-input-label for="login" value="Usuario o correo" />
        <x-text-input wire:model="form.login" id="login" type="text" required autofocus
                      autocomplete="username" autocapitalize="none" spellcheck="false" />
        <x-input-error :messages="$errors->get('form.login')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" value="Contraseña" />
        <x-text-input wire:model="form.password" id="password" type="password" required
                      autocomplete="current-password" />
        <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
    </div>

    <label for="remember" class="flex min-h-11 items-center gap-3 text-sm text-anil-800">
        <input wire:model="form.remember" id="remember" type="checkbox"
               class="size-5 rounded border-anil-300 text-rosa-500 focus:ring-rosa-500/30">
        Mantener la sesión iniciada
    </label>

    <x-primary-button class="w-full" wire:loading.attr="disabled">
        <span wire:loading.remove>Entrar</span>
        <span wire:loading>Verificando…</span>
    </x-primary-button>
</form>
