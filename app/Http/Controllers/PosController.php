<?php

namespace App\Http\Controllers;

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Models\Articulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda de artículos para la pantalla de caja (el escáner manda un código y Enter).
 * Devuelve JSON mínimo para que la caja responda al instante.
 */
class PosController extends Controller
{
    public function buscar(Request $request, RegistrarEscaneo $registrar): JsonResponse
    {
        // Parámetros con forma de arreglo (?q[]=1) se tratan como vacíos en lugar de dar error 500.
        $texto = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $medio = is_string($request->query('medio')) ? MedioEscaneo::tryFrom($request->query('medio')) : null;

        // La caja marca con escaneo=1 lo que llega por el campo de captura (no la búsqueda por nombre). Solo se anota
        // si la petición la hace la propia pantalla (X-Requested-With), no un enlace abierto desde otro sitio.
        if ($request->boolean('escaneo') && $request->ajax()) {
            $registrar($texto, OrigenEscaneo::Caja, $medio ?? MedioEscaneo::Lector, $request->user());
        }

        if ($texto === '' || mb_strlen($texto) > 80) {
            return $this->respuesta([], false);
        }

        $exacto = Articulo::porClave($texto);

        if ($exacto && $exacto->activo) {
            return $this->respuesta([$this->formato($exacto->loadMissing('unidad'))], true);
        }

        $articulos = Articulo::query()
            ->with('unidad')
            ->activos()
            ->buscar($texto)
            ->orderBy('descripcion')
            ->limit(15)
            ->get();

        return $this->respuesta($articulos->map(fn (Articulo $a) => $this->formato($a))->all(), false);
    }

    /** Precios vigentes de artículos que ya estaban en un carrito guardado. */
    /**
     * Cuadrícula de productos de la caja en PC y tablet (decisión 86): los más vendidos de los últimos 30 días
     * (24) o, con ?depto=, los de ese departamento (60), siempre de más a menos vendido y luego por nombre.
     */
    public function rapidos(Request $request): JsonResponse
    {
        $depto = is_string($request->query('depto')) && ctype_digit($request->query('depto')) ? (int) $request->query('depto') : null;

        $vendidas = DB::table('venta_partidas')
            ->join('ventas', 'ventas.id', '=', 'venta_partidas.venta_id')
            ->where('ventas.estado', 'completada')
            ->where('ventas.created_at', '>=', now()->subDays(30))
            ->groupBy('venta_partidas.articulo_id')
            ->selectRaw('venta_partidas.articulo_id, SUM(venta_partidas.cantidad) as vendidas');

        $articulos = Articulo::query()->with('unidad')->activos()
            ->leftJoinSub($vendidas, 'v', 'v.articulo_id', '=', 'articulos.id')
            ->when($depto, fn ($q) => $q->where('articulos.departamento_id', $depto))
            ->orderByRaw('COALESCE(v.vendidas, 0) DESC')
            ->orderBy('articulos.descripcion')
            ->limit($depto ? 60 : 24)
            ->get(['articulos.*']);

        return response()->json([
            'articulos' => $articulos->map(fn (Articulo $a) => $this->formato($a) + ['imagen' => $a->imagenUrl()])->all(),
        ])->header('Cache-Control', 'no-store');
    }

    public function porIds(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(300)->values();

        $articulos = Articulo::query()->with('unidad')->activos()->whereIn('id', $ids)->get();

        return $this->respuesta($articulos->map(fn (Articulo $a) => $this->formato($a))->all(), false);
    }

    /** @return array<string, mixed> */
    private function formato(Articulo $a): array
    {
        return self::articuloJson($a);
    }

    /** @return array<string, mixed> */
    public static function articuloJson(Articulo $a): array
    {
        $a->loadMissing('unidad');

        return [
            'id' => $a->id,
            'clave' => $a->clave,
            'descripcion' => $a->descripcion,
            'unidad' => $a->unidad->abreviatura,
            'decimales' => (bool) $a->unidad->permite_decimales,
            'granel' => (bool) $a->granel,
            'inventariable' => (bool) $a->inventariable,
            'existencia' => (string) $a->existencia,
            'precios' => [(string) $a->precio_1, $a->precio_2 !== null ? (string) $a->precio_2 : null, $a->precio_3 !== null ? (string) $a->precio_3 : null],
        ];
    }

    /** @param  list<array<string, mixed>>  $articulos */
    private function respuesta(array $articulos, bool $exacto): JsonResponse
    {
        return response()->json(['exacto' => $exacto, 'articulos' => $articulos])
            ->header('Cache-Control', 'no-store');
    }
}
