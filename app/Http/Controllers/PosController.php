<?php

namespace App\Http\Controllers;

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Models\Articulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
