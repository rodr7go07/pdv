<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case Encargado = 'Encargado';
    case Cajero = 'Cajero';

    /** Lugar del rol en las listas: primero los del sistema en su orden y después los de la tienda. */
    public static function orden(string $nombre): int
    {
        $i = array_search($nombre, array_map(fn (self $r) => $r->value, self::cases()), true);

        return $i === false ? 99 : $i;
    }

    /** Los tres roles que trae el sistema: no se borran ni se renombran (el Administrador tampoco cambia de permisos). */
    public static function esBase(string $nombre): bool
    {
        return self::tryFrom($nombre) !== null;
    }

    /**
     * Permisos con los que nace cada rol (después se pueden cambiar desde la pantalla Roles, salvo los del Administrador).
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => Permiso::cases(),

            self::Encargado => array_values(array_filter(
                Permiso::cases(),
                fn (Permiso $p) => ! in_array($p, [Permiso::UsuariosAdministrar, Permiso::ConfiguracionEditar, Permiso::RolesAdministrar], true),
            )),

            self::Cajero => [
                Permiso::ArticulosVer,
                Permiso::VentasCobrar,
                Permiso::CajaAbrir,
                Permiso::CajaCorte,
                Permiso::ClientesVer,
                Permiso::ClientesAbonar,
            ],
        };
    }
}
