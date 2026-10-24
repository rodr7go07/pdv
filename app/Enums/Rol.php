<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case Encargado = 'Encargado';
    case Cajero = 'Cajero';

    /**
     * Permisos que recibe cada rol al sembrar la base.
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => Permiso::cases(),

            self::Encargado => array_values(array_filter(
                Permiso::cases(),
                fn (Permiso $p) => ! in_array($p, [Permiso::UsuariosAdministrar, Permiso::ConfiguracionEditar], true),
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
