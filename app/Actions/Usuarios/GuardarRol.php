<?php

namespace App\Actions\Usuarios;

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Crea o edita un rol con sus permisos (permiso roles.administrar).
 *
 *  - El Administrador siempre tiene todos los permisos: no se edita.
 *  - Encargado y Cajero se pueden editar, pero no renombrar.
 *  - Nadie puede dar un permiso que él mismo no tiene (así un rol no sirve para subirse de nivel).
 */
class GuardarRol
{
    /**
     * @param  list<string>  $permisos  valores de App\Enums\Permiso
     *
     * @throws ValidationException
     */
    public function __invoke(?Role $rol, string $nombre, array $permisos, User $quienLoHace): Role
    {
        abort_unless($quienLoHace->can('roles.administrar'), 403, 'No tienes permiso para administrar roles.');

        $nombre = trim((string) preg_replace('/\s+/u', ' ', $nombre));
        $permisos = array_values(array_unique(array_map('strval', $permisos)));

        if ($rol?->name === Rol::Administrador->value) {
            throw ValidationException::withMessages(['rol' => 'El rol Administrador siempre tiene todos los permisos y no se puede cambiar.']);
        }

        Validator::make(['nombre' => $nombre, 'permisos' => $permisos], [
            'nombre' => ['required', 'string', 'min:2', 'max:40', Rule::unique('roles', 'name')->ignore($rol?->id)],
            'permisos' => ['array'],
            'permisos.*' => [Rule::enum(Permiso::class)],
        ], [
            'nombre.required' => 'Escribe el nombre del rol.',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'permisos.*.enum' => 'Hay un permiso que no existe.',
        ], ['nombre' => 'nombre'])->validate();

        if ($rol && Rol::esBase($rol->name) && $nombre !== $rol->name) {
            throw ValidationException::withMessages(['nombre' => 'Los roles que trae el sistema no se pueden renombrar.']);
        }

        // Solo se pueden dar permisos que quien edita ya tiene.
        $actuales = $rol ? $rol->permissions->pluck('name')->all() : [];
        $ajenos = array_values(array_filter(array_diff($permisos, $actuales), fn (string $p) => ! $quienLoHace->can($p)));

        if ($ajenos !== []) {
            $etiquetas = collect($ajenos)->map(fn (string $p) => Permiso::from($p)->etiqueta())->implode(', ');

            throw ValidationException::withMessages(['permisos' => "No puedes dar permisos que tú no tienes: {$etiquetas}."]);
        }

        return DB::transaction(function () use ($rol, $nombre, $permisos) {
            if ($rol) {
                $rol->update(['name' => $nombre]);
            } else {
                $rol = Role::create(['name' => $nombre, 'guard_name' => 'web']);
            }

            $rol->syncPermissions($permisos);

            return $rol->refresh();
        });
    }
}
