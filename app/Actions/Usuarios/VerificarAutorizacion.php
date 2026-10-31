<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Comprueba que un supervisor autoriza algo con su usuario y contraseña, sin cambiar de sesión.
 * Los intentos fallidos se limitan por quien pide la autorización, y el mensaje no dice si falló
 * el usuario, la contraseña o el permiso.
 */
class VerificarAutorizacion
{
    public const INTENTOS = 5;

    public const SEGUNDOS = 300;

    /**
     * @throws ValidationException
     */
    public function __invoke(string $usuario, string $password, string $permiso, User $solicitante): User
    {
        $llave = 'autorizacion:'.$solicitante->id;

        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($llave) / 60);

            throw ValidationException::withMessages(['autorizacion' => "Demasiados intentos. Espera {$minutos} ".($minutos === 1 ? 'minuto' : 'minutos').' para volver a intentar.']);
        }

        $supervisor = User::query()->where('usuario', mb_strtolower(trim($usuario)))->where('activo', true)->first();

        // Siempre se compara una contraseña, aunque el usuario no exista, para que el tiempo de respuesta no lo delate.
        $correcta = Hash::check($password, $supervisor->password ?? Hash::make('sin-usuario'));

        if (! $supervisor || ! $correcta || ! $supervisor->can($permiso)) {
            RateLimiter::hit($llave, self::SEGUNDOS);

            throw ValidationException::withMessages(['autorizacion' => 'No se pudo autorizar: revisa el usuario y la contraseña, y que esa persona pueda autorizar cancelaciones.']);
        }

        RateLimiter::clear($llave);

        return $supervisor;
    }
}
