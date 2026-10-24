<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /** Nombre de usuario o correo electrónico. */
    #[Validate('required|string|max:255', as: 'usuario')]
    public string $login = '';

    #[Validate('required|string', as: 'contraseña')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Intenta autenticar con usuario o correo. Solo entran usuarios activos.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $campo = str_contains($this->login, '@') ? 'email' : 'usuario';

        $credenciales = [
            $campo => $this->login,
            'password' => $this->password,
            'activo' => true,
        ];

        if (! Auth::attempt($credenciales, $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipKey(), 300);

            throw ValidationException::withMessages([
                'form.login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        // Límite por usuario+IP y otro por IP sola (cambiar de nombre de usuario no reinicia el conteo).
        $llave = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->ipKey(), 30) => $this->ipKey(),
            default => null,
        };

        if ($llave === null) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($llave);

        throw ValidationException::withMessages([
            'form.login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function ipKey(): string
    {
        return 'login-ip|'.request()->ip();
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }
}
