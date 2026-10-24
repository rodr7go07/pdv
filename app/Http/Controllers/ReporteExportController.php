<?php

namespace App\Http\Controllers;

use App\Exports\ReporteExport;
use App\Services\ReporteVentas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exporta a Excel el reporte que se está viendo, con los mismos filtros. */
class ReporteExportController extends Controller
{
    public const SECCIONES = ['resumen', 'periodo', 'departamento', 'articulo', 'cajero', 'pago', 'inventario'];

    public function __invoke(Request $request, string $seccion): BinaryFileResponse
    {
        abort_unless($request->user()->can('reportes.ver'), 403);
        abort_unless(in_array($seccion, self::SECCIONES, true), 404);

        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'caja' => ['nullable', 'integer'],
            'cajero' => ['nullable', 'integer'],
            'agrupar' => ['nullable', 'in:dia,semana,mes'],
            'orden' => ['nullable', 'in:importe,cantidad,utilidad'],
        ]);

        $reporte = new ReporteVentas(
            $datos['desde'] ?? CarbonImmutable::now()->startOfMonth(),
            $datos['hasta'] ?? CarbonImmutable::now(),
            isset($datos['caja']) ? (int) $datos['caja'] : null,
            isset($datos['cajero']) ? (int) $datos['cajero'] : null,
        );

        [$titulo, $encabezados, $filas] = $this->armar($seccion, $reporte, $datos['agrupar'] ?? 'dia', $datos['orden'] ?? 'importe');

        $nombre = 'reporte-'.$seccion.'-'.$reporte->desde->toDateString().'-a-'.$reporte->hasta->toDateString().'.xlsx';

        return Excel::download(new ReporteExport($titulo, $encabezados, $filas), $nombre);
    }

    /** @return array{0: string, 1: list<string>, 2: list<list<scalar|null>>} */
    private function armar(string $seccion, ReporteVentas $r, string $agrupar, string $orden): array
    {
        $n = fn ($v) => (float) $v;
        $cabecera = ['Vendido', 'Devuelto', 'Neto', 'Costo', 'Utilidad', 'Margen %'];
        $cifras = fn (array $f) => [$n($f['bruto']), $n($f['devuelto']), $n($f['neto']), $n($f['costo']), $n($f['utilidad']), $f['margen'] === null ? null : $n($f['margen'])];

        return match ($seccion) {
            'resumen' => (function () use ($r, $n) {
                $x = $r->resumen();

                return ['Resumen', ['Concepto', 'Valor'], [
                    ['Periodo', $r->desde->toDateString().' al '.$r->hasta->toDateString()],
                    ['Ventas', $x['ventas']], ['Ventas canceladas', $x['canceladas']], ['Vendido', $n($x['bruto'])], ['Devoluciones', $n($x['devuelto'])],
                    ['Venta neta', $n($x['neto'])], ['Descuentos', $n($x['descuento'])], ['Impuestos cobrados', $n($x['impuestos'])],
                    ['Costo', $n($x['costo'])], ['Utilidad bruta', $n($x['utilidad'])], ['Margen %', $x['margen'] === null ? null : $n($x['margen'])],
                    ['Ticket promedio', $n($x['ticket_promedio'])],
                ]];
            })(),
            'periodo' => ['Ventas por periodo', ['Periodo', 'Ventas', ...$cabecera], $r->porPeriodo($agrupar)->map(fn ($f) => [$f['etiqueta'], $f['ventas'], ...$cifras($f)])->all()],
            'departamento' => ['Por departamento', ['Departamento', ...$cabecera], $r->porDepartamento()->map(fn ($f) => [$f['nombre'], ...$cifras($f)])->all()],
            'articulo' => ['Por artículo', ['Clave', 'Artículo', 'Cantidad', ...$cabecera], $r->porArticulo(500, $orden)->map(fn ($f) => [$f['clave'], $f['nombre'], $n($f['cantidad']), ...$cifras($f)])->all()],
            'cajero' => ['Por cajero', ['Cajero', 'Ventas', ...$cabecera], $r->porCajero()->map(fn ($f) => [$f['nombre'], $f['ventas'], ...$cifras($f)])->all()],
            'pago' => ['Por forma de pago', ['Forma de pago', 'Ventas', 'Monto'], $r->porFormaPago()->map(fn ($f) => [$f['etiqueta'], $f['ventas'], $n($f['monto'])])->all()],
            'inventario' => (function () use ($n) {
                $v = ReporteVentas::valorInventario();

                return ['Valor del inventario', ['Departamento', 'Artículos', 'Piezas', 'Valor a costo', 'Valor a precio de venta'],
                    $v['filas']->map(fn ($f) => [$f['nombre'], $f['articulos'], $n($f['piezas']), $n($f['costo']), $n($f['venta'])])->all()];
            })(),
        };
    }
}
