<?php

namespace App\Enums;

/**
 * Catálogo de permisos del sistema (spatie/laravel-permission).
 * El valor es el nombre guardado en la tabla `permissions`.
 */
enum Permiso: string
{
    case ArticulosVer = 'articulos.ver';
    case ArticulosCrear = 'articulos.crear';
    case ArticulosEditar = 'articulos.editar';
    case ArticulosEditarPrecio = 'articulos.editar_precio';
    case ArticulosImportar = 'articulos.importar';
    case CatalogosAdministrar = 'catalogos.administrar';

    case InventarioVer = 'inventario.ver';
    case InventarioAjustar = 'inventario.ajustar';
    case InventarioConteo = 'inventario.conteo';
    case ComprasVer = 'compras.ver';
    case ComprasRegistrar = 'compras.registrar';
    case ComprasCancelar = 'compras.cancelar';
    case ProveedoresAdministrar = 'proveedores.administrar';

    case VentasCobrar = 'ventas.cobrar';
    case VentasVerTodas = 'ventas.ver_todas';
    case VentasDescuento = 'ventas.descuento';
    case VentasCancelar = 'ventas.cancelar';
    case VentasDevolver = 'ventas.devolver';

    case CajaAbrir = 'caja.abrir';
    case CajaRetiro = 'caja.retiro';
    case CajaCorte = 'caja.corte';

    case ReportesVer = 'reportes.ver';

    case ConfiguracionEditar = 'configuracion.editar';
    case UsuariosAdministrar = 'usuarios.administrar';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
