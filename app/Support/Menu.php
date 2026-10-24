<?php

namespace App\Support;

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menú principal. Cada módulo se registra aquí con el permiso que lo habilita,
 * así la barra lateral, la barra inferior y el tablero muestran lo mismo.
 * La autorización real sigue verificándose en las rutas y en el backend.
 *
 * 'movil' marca los accesos que van en la barra inferior del teléfono (máximo 4);
 * el resto se abre desde el botón «Más».
 */
final class Menu
{
    public const GRUPOS = ['Principal', 'Mercancía', 'Administración', 'Ayuda'];

    /**
     * @return Collection<int, array{etiqueta: string, descripcion: string, ruta: string, icono: string, coincide: string, permiso: ?Permiso, grupo: string, movil: bool}>
     */
    public static function para(?User $user): Collection
    {
        return collect([
            ['Inicio', 'Resumen y accesos rápidos.', 'dashboard', 'house', 'dashboard', null, 'Principal', true],
            ['Artículos', 'Catálogo de productos, precios y claves.', 'articulos.index', 'package', 'articulos.*', Permiso::ArticulosVer, 'Mercancía', true],
            ['Inventario', 'Existencias, kardex, ajustes y conteo físico.', 'inventario.index', 'stack', 'inventario.*', Permiso::InventarioVer, 'Mercancía', true],
            ['Compras', 'Mercancía que recibes y sus proveedores.', 'compras.index', 'truck', 'compras.*', Permiso::ComprasVer, 'Mercancía', false],
            ['Catálogos', 'Departamentos, unidades e impuestos.', 'catalogos', 'folders', 'catalogos', Permiso::CatalogosAdministrar, 'Administración', false],
            ['Configuración', 'Datos de la tienda, ticket y costos.', 'configuracion', 'gear', 'configuracion', Permiso::ConfiguracionEditar, 'Administración', false],
            ['Guía de uso', 'Cómo usar el sistema, paso a paso.', 'guia', 'lifebuoy', 'guia', null, 'Ayuda', false],
            ['Mi cuenta', 'Tus datos y tu contraseña.', 'profile', 'user-circle', 'profile', null, 'Ayuda', true],
        ])->map(fn (array $i) => [
            'etiqueta' => $i[0], 'descripcion' => $i[1], 'ruta' => $i[2], 'icono' => $i[3],
            'coincide' => $i[4], 'permiso' => $i[5], 'grupo' => $i[6], 'movil' => $i[7],
        ])->filter(fn (array $item) => $item['permiso'] === null || $user?->can($item['permiso']->value))
            ->values();
    }

    /**
     * Reparte el menú para la barra inferior del teléfono.
     *
     * @param  Collection<int, array<string, mixed>>  $menu
     * @return array{visibles: Collection, mas: bool}
     */
    public static function barraMovil(Collection $menu): array
    {
        if ($menu->count() <= 5) {
            return ['visibles' => $menu, 'mas' => false];
        }

        return ['visibles' => $menu->where('movil', true)->take(4)->values(), 'mas' => true];
    }
}
