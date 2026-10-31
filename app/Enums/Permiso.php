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
    case VentasAutorizarCancelacion = 'ventas.autorizar_cancelacion';
    case VentasDevolver = 'ventas.devolver';

    case CajaAbrir = 'caja.abrir';
    case CajaRetiro = 'caja.retiro';
    case CajaCorte = 'caja.corte';
    case CajaSupervisar = 'caja.supervisar';
    case CajasAdministrar = 'cajas.administrar';

    case ClientesVer = 'clientes.ver';
    case ClientesAdministrar = 'clientes.administrar';
    case ClientesCredito = 'clientes.credito';
    case ClientesAbonar = 'clientes.abonar';

    case ReportesVer = 'reportes.ver';

    case ConfiguracionEditar = 'configuracion.editar';
    case UsuariosAdministrar = 'usuarios.administrar';
    case RolesAdministrar = 'roles.administrar';

    /** Lo que significa el permiso, en palabras de todos los días. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::ArticulosVer => 'Ver artículos y precios',
            self::ArticulosCrear => 'Dar de alta artículos',
            self::ArticulosEditar => 'Editar artículos',
            self::ArticulosEditarPrecio => 'Cambiar costos y precios',
            self::ArticulosImportar => 'Importar artículos desde Excel',
            self::CatalogosAdministrar => 'Administrar departamentos, unidades e impuestos',
            self::InventarioVer => 'Ver existencias y movimientos',
            self::InventarioAjustar => 'Ajustar inventario (mermas, correcciones)',
            self::InventarioConteo => 'Hacer conteos físicos',
            self::ComprasVer => 'Ver las compras',
            self::ComprasRegistrar => 'Registrar compras de mercancía',
            self::ComprasCancelar => 'Cancelar compras',
            self::ProveedoresAdministrar => 'Administrar proveedores',
            self::VentasCobrar => 'Cobrar en la caja',
            self::VentasVerTodas => 'Ver las ventas de todos los cajeros',
            self::VentasDescuento => 'Dar descuentos',
            self::VentasCancelar => 'Cancelar ventas',
            self::VentasAutorizarCancelacion => 'Autorizar que un cajero cancele su venta (con su usuario y contraseña)',
            self::VentasDevolver => 'Hacer devoluciones',
            self::CajaAbrir => 'Abrir su turno de caja',
            self::CajaRetiro => 'Registrar entradas y retiros de efectivo',
            self::CajaCorte => 'Hacer cortes y cerrar su turno',
            self::CajaSupervisar => 'Ver y cerrar los turnos de todos',
            self::CajasAdministrar => 'Administrar las cajas',
            self::ClientesVer => 'Ver y elegir clientes',
            self::ClientesAdministrar => 'Dar de alta y editar clientes',
            self::ClientesCredito => 'Autorizar crédito y listas de precios',
            self::ClientesAbonar => 'Recibir abonos de clientes',
            self::ReportesVer => 'Ver reportes y el tablero',
            self::ConfiguracionEditar => 'Cambiar la configuración de la tienda',
            self::UsuariosAdministrar => 'Administrar usuarios',
            self::RolesAdministrar => 'Crear y editar roles y sus permisos',
        };
    }

    /** Módulo al que pertenece, para agrupar los permisos en la pantalla de roles. */
    public function grupo(): string
    {
        return match ($this) {
            self::ArticulosVer, self::ArticulosCrear, self::ArticulosEditar, self::ArticulosEditarPrecio, self::ArticulosImportar => 'Artículos',
            self::InventarioVer, self::InventarioAjustar, self::InventarioConteo => 'Inventario',
            self::ComprasVer, self::ComprasRegistrar, self::ComprasCancelar, self::ProveedoresAdministrar => 'Compras y proveedores',
            self::VentasCobrar, self::VentasVerTodas, self::VentasDescuento, self::VentasCancelar, self::VentasAutorizarCancelacion, self::VentasDevolver => 'Ventas',
            self::CajaAbrir, self::CajaRetiro, self::CajaCorte, self::CajaSupervisar, self::CajasAdministrar => 'Caja',
            self::ClientesVer, self::ClientesAdministrar, self::ClientesCredito, self::ClientesAbonar => 'Clientes y crédito',
            self::ReportesVer => 'Reportes',
            self::CatalogosAdministrar, self::ConfiguracionEditar, self::UsuariosAdministrar, self::RolesAdministrar => 'Administración',
        };
    }
}
