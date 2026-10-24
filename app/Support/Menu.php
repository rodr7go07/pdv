<?php

namespace App\Support;

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menú principal. Cada módulo se registra aquí con el permiso que lo habilita,
 * así la barra lateral, la barra inferior y el tablero muestran lo mismo.
 * La autorización real sigue verificándose en las rutas y en el backend.
 */
final class Menu
{
    /**
     * @return Collection<int, array{etiqueta: string, descripcion: string, ruta: string, icono: string, coincide: string, permiso: ?Permiso}>
     */
    public static function para(?User $user): Collection
    {
        return collect([
            [
                'etiqueta' => 'Inicio',
                'descripcion' => 'Resumen y accesos rápidos.',
                'ruta' => 'dashboard',
                'icono' => 'house',
                'coincide' => 'dashboard',
                'permiso' => null,
            ],
            [
                'etiqueta' => 'Configuración',
                'descripcion' => 'Datos de la tienda y encabezado del ticket.',
                'ruta' => 'configuracion',
                'icono' => 'gear',
                'coincide' => 'configuracion',
                'permiso' => Permiso::ConfiguracionEditar,
            ],
            [
                'etiqueta' => 'Mi cuenta',
                'descripcion' => 'Tus datos y tu contraseña.',
                'ruta' => 'profile',
                'icono' => 'user-circle',
                'coincide' => 'profile',
                'permiso' => null,
            ],
        ])->filter(fn (array $item) => $item['permiso'] === null || $user?->can($item['permiso']->value))
            ->values();
    }
}
