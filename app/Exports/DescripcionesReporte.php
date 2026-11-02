<?php

namespace App\Exports;

/**
 * Qué significa cada columna de los reportes descargables, en palabras sencillas.
 * El Excel las muestra a un costado de la tabla (ver ReporteExport).
 */
final class DescripcionesReporte
{
    /** Cifras que repiten varios reportes de ventas. */
    private const VENTAS = [
        'Ventas' => 'Cuántas ventas se hicieron (las canceladas no cuentan).',
        'Vendido' => 'Lo cobrado por las ventas: con impuestos y ya con los descuentos, antes de restar devoluciones.',
        'Devuelto' => 'Dinero que se regresó a clientes por devoluciones de esas ventas.',
        'Neto' => 'Vendido menos Devuelto: lo que realmente quedó de la venta.',
        'Costo' => 'Lo que costó la mercancía vendida (al costo del día de la venta), sin la que se devolvió.',
        'Utilidad' => 'Lo que ganaste en los productos: venta sin impuestos menos Costo. Todavía no restas renta, luz ni sueldos.',
        'Margen %' => 'Utilidad entre la venta sin impuestos, por 100. Qué parte de cada peso vendido es ganancia.',
    ];

    /** @return array<string, string> columna => significado */
    public static function para(string $seccion): array
    {
        return match ($seccion) {
            'resumen' => [
                'Periodo' => 'Fechas que abarca el reporte.',
                'Ventas' => self::VENTAS['Ventas'],
                'Ventas canceladas' => 'Ventas que se cancelaron en el periodo. No suman en ninguna cifra.',
                'Vendido' => self::VENTAS['Vendido'],
                'Devoluciones' => self::VENTAS['Devuelto'],
                'Venta neta' => self::VENTAS['Neto'],
                'Descuentos' => 'Total descontado a los clientes en esas ventas.',
                'Impuestos cobrados' => 'Impuestos (IVA, IEPS) incluidos en lo vendido.',
                'Costo' => self::VENTAS['Costo'],
                'Utilidad bruta' => self::VENTAS['Utilidad'],
                'Margen %' => self::VENTAS['Margen %'],
                'Ticket promedio' => 'Lo que gasta en promedio cada cliente: Vendido entre Ventas.',
            ],
            'periodo' => ['Periodo' => 'Día, semana o mes en que se agrupan las ventas.'] + self::VENTAS,
            'departamento' => ['Departamento' => 'Departamento que tiene hoy el artículo.'] + array_diff_key(self::VENTAS, ['Ventas' => 1]),
            'articulo' => [
                'Clave' => 'Código del artículo.',
                'Artículo' => 'Nombre del artículo.',
                'Cantidad' => 'Piezas, kilos o litros vendidos.',
            ] + array_diff_key(self::VENTAS, ['Ventas' => 1]),
            'cajero' => ['Cajero' => 'Quién atendió las ventas.'] + self::VENTAS,
            'pago' => [
                'Forma de pago' => 'Efectivo, tarjeta, transferencia o crédito.',
                'Ventas' => 'En cuántas ventas se usó esta forma de pago (una venta mixta cuenta en cada una).',
                'Monto' => 'Dinero cobrado con esta forma. En efectivo no incluye el cambio entregado.',
            ],
            'inventario' => [
                'Departamento' => 'Departamento del artículo.',
                'Artículos' => 'Cuántos artículos distintos tienen existencia.',
                'Piezas' => 'Suma de las existencias (piezas, kilos o litros).',
                'Valor a costo' => 'Existencia por costo: cuánto te costó la mercancía que tienes.',
                'Valor a precio de venta' => 'Existencia por precio 1: cuánto cobrarías si la vendes toda.',
            ],
            default => [],
        };
    }

    /**
     * Lo mismo para la pantalla de Reportes: con los nombres de columna tal como se ven ahí.
     *
     * @return array<string, string>
     */
    public static function pantalla(string $seccion): array
    {
        $v = self::VENTAS;
        $cifras = ['Neto' => 'Lo vendido menos lo devuelto: lo que realmente quedó de la venta.', 'Costo' => $v['Costo'], 'Utilidad' => $v['Utilidad'], 'Margen' => $v['Margen %']];

        return match ($seccion) {
            'resumen' => [
                'Ventas' => $v['Ventas'],
                'Venta neta' => 'Lo vendido menos las devoluciones: lo que realmente quedó de la venta.',
                'Ticket promedio' => 'Lo que gasta en promedio cada cliente: lo vendido entre el número de ventas.',
                'Utilidad bruta' => $v['Utilidad'],
                'Margen' => $v['Margen %'],
                'Costo de lo vendido' => $v['Costo'],
                'Descuentos dados' => 'Total descontado a los clientes en esas ventas.',
                'Impuestos cobrados' => 'Impuestos (IVA, IEPS) incluidos en lo vendido.',
                'Devoluciones' => $v['Devuelto'],
            ],
            'periodo' => ['Periodo' => 'Día, semana o mes en que se agrupan las ventas.', 'Ventas' => $v['Ventas']] + $cifras,
            'departamento' => ['Departamento' => 'Departamento que tiene hoy el artículo.'] + $cifras,
            'articulo' => ['Artículo' => 'Nombre y clave del artículo.', 'Piezas' => 'Piezas, kilos o litros vendidos.'] + $cifras,
            'cajero' => ['Cajero' => 'Quién atendió las ventas.', 'Ventas' => $v['Ventas']] + $cifras,
            'pago' => [
                'Forma de pago' => 'Efectivo, tarjeta, transferencia o crédito (cada tarjeta de arriba).',
                'Monto' => 'Dinero cobrado con esta forma. En efectivo no incluye el cambio entregado.',
                'Ventas' => 'En cuántas ventas se usó (una venta mixta cuenta en cada forma).',
                '%' => 'Qué parte del total cobrado se pagó así.',
            ],
            'inventario' => [
                'Departamento' => 'Departamento del artículo.',
                'Artículos' => 'Cuántos artículos distintos tienen existencia.',
                'A costo' => 'Existencia por costo: cuánto te costó la mercancía que tienes.',
                'A precio de venta' => 'Existencia por precio 1: cuánto cobrarías si la vendes toda.',
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public static function sugerenciaCompra(int $diasVenta): array
    {
        return [
            'Clave' => 'Código del artículo.',
            'Artículo' => 'Nombre del artículo.',
            'Unidad' => 'Cómo se cuenta: pieza, kilo, litro…',
            'Proveedor' => 'Proveedor principal del artículo.',
            'Existencia' => 'Cuánto hay hoy en la tienda.',
            'Mínimo' => 'Existencia mínima que quieres tener (vacío si no la definiste).',
            'Vendido ('.$diasVenta.' días)' => 'Lo vendido en ese periodo, sin lo devuelto. Con esto se calcula el ritmo de venta.',
            'Días que alcanza' => 'Cuántos días dura la existencia actual al ritmo de venta.',
            'Comprar' => 'Cantidad que conviene pedir para cubrir los días elegidos sin quedar bajo el mínimo (con tus cambios, si los hiciste).',
            'Costo unitario' => 'Costo de cada pieza: el de la última compra, o el del artículo si nunca se compró.',
            'Costo estimado' => 'Comprar por Costo unitario: lo que costaría el pedido de ese artículo.',
        ];
    }
}
