<?php

namespace App\Actions\Usuarios;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Alta y edición de usuarios (solo con el permiso usuarios.administrar).
 * Protege que la tienda nunca se quede sin un administrador activo y que nadie se desactive a sí mismo.
 */
class GuardarUsuario
{
    /**
     * @param  array{name: string, usuario: string, email?: ?string, password?: ?string, rol: string, activo?: bool}  $datos
     *
     * @throws ValidationException
     */
    public function __invoke(?User $usuario, array $datos, User $quienLoHace): User
    {
        abort_unless($quienLoHace->can('usuarios.administrar'), 403, 'No tienes permiso para administrar usuarios.');

        $datos['usuario'] = mb_strtolower(trim((string) ($datos['usuario'] ?? '')));
        $datos['email'] = ($e = trim((string) ($datos['email'] ?? ''))) === '' ? null : mb_strtolower($e);
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        $validado = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'usuario')->ignore($usuario?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'password' => [$usuario ? 'nullable' : 'required', 'string', Password::min(8)],
            'rol' => ['required', Rule::enum(Rol::class)],
        ], [
            'usuario.regex' => 'El usuario solo puede tener letras minúsculas, números, punto, guion y guion bajo (sin espacios).',
            'usuario.unique' => 'Ese usuario ya existe.',
            'email.unique' => 'Ese correo ya lo usa otra persona.',
            'password.required' => 'Escribe una contraseña para el usuario nuevo.',
        ], ['name' => 'nombre', 'usuario' => 'usuario', 'email' => 'correo', 'password' => 'contraseña', 'rol' => 'rol'])->validate();

        return DB::transaction(function () use ($usuario, $datos, $validado, $quienLoHace) {
            if ($usuario) {
                $usuario = User::query()->lockForUpdate()->findOrFail($usuario->id);

                if ($usuario->id === $quienLoHace->id && ! $datos['activo']) {
                    throw ValidationException::withMessages(['activo' => 'No puedes desactivar tu propio usuario.']);
                }

                $eraAdmin = $usuario->hasRole(Rol::Administrador->value) && $usuario->activo;
                $seguiraAdmin = $validado['rol'] === Rol::Administrador->value && $datos['activo'];

                if ($eraAdmin && ! $seguiraAdmin && ! $this->hayOtroAdministrador($usuario)) {
                    throw ValidationException::withMessages(['rol' => 'La tienda necesita al menos un administrador activo. Nombra a otro antes de quitar o desactivar a este.']);
                }
            }

            $usuario ??= new User;

            $usuario->fill([
                'name' => trim($validado['name']),
                'usuario' => $validado['usuario'],
                'email' => $validado['email'],
                'activo' => $datos['activo'],
            ]);

            if (! empty($validado['password'])) {
                $usuario->password = $validado['password'];
            }

            $usuario->save();
            $usuario->syncRoles($validado['rol']);

            return $usuario->load('roles');
        });
    }

    private function hayOtroAdministrador(User $excepto): bool
    {
        return User::query()->role(Rol::Administrador->value)->where('activo', true)->whereKeyNot($excepto->id)->exists();
    }
}
