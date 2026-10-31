<?php

namespace App\Actions\Usuarios;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Borra un rol creado por la tienda, siempre que ya nadie lo tenga. Los tres roles base no se borran. */
class EliminarRol
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Role $rol, User $quienLoHace): void
    {
        abort_unless($quienLoHace->can('roles.administrar'), 403, 'No tienes permiso para administrar roles.');

        if (Rol::esBase($rol->name)) {
            throw ValidationException::withMessages(['rol' => 'Los roles que trae el sistema no se pueden borrar.']);
        }

        $usuarios = User::query()->role($rol->name)->count();

        if ($usuarios > 0) {
            throw ValidationException::withMessages(['rol' => "«{$rol->name}» lo tienen {$usuarios} ".($usuarios === 1 ? 'usuario' : 'usuarios').': cámbiales el rol primero.']);
        }

        $rol->delete();
    }
}
