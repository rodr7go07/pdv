<?php

namespace App\Enums;

/** Pantalla donde se escaneó un código. El valor es lo que se guarda en `escaneos.origen`. */
enum OrigenEscaneo: string
{
    case Caja = 'caja';
    case Compras = 'compras';
    case Ajustes = 'ajustes';
    case Conteo = 'conteo';
    case Etiquetas = 'etiquetas';
    case Articulos = 'articulos';
    case ArticuloClave = 'articulo_clave';
    case Inventario = 'inventario';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Caja => 'Venta',
            self::Compras => 'Compras',
            self::Ajustes => 'Ajuste de inventario',
            self::Conteo => 'Conteo físico',
            self::Etiquetas => 'Etiquetas',
            self::Articulos => 'Lista de artículos',
            self::ArticuloClave => 'Alta o edición de artículo',
            self::Inventario => 'Lista de inventario',
        };
    }
}
