<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\Turno;
use App\Models\User;
use App\Support\Numero;
use Illuminate\Support\Collection;

/**
 * Hoja de existencias para la térmica (decisión 83): los artículos elegidos en Inventario con su clave, existencia,
 * mínimo/máximo y una raya para anotar lo que se contó. El mismo texto sirve para el navegador y para ESC/POS.
 */
final class ListaExistencias
{
    public const MAXIMO = 300;

    /**
     * Ids válidos en el orden de la descripción (los que no existen o están inactivos se ignoran).
     *
     * @param  array<int|string>  $ids
     * @return Collection<int, Articulo>
     */
    public static function articulos(array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->take(self::MAXIMO)->values();

        return Articulo::query()->with('unidad:id,abreviatura')->activos()->whereIn('id', $ids)->orderBy('descripcion')->get();
    }

    /** Ancho del papel: el de la caja del turno abierto; si no hay, el de la primera caja activa; si no, 58 mm. */
    public static function anchoPapel(User $usuario): int
    {
        $caja = Turno::abiertoDe($usuario)?->caja ?? Caja::query()->activas()->orderBy('id')->first();

        return (int) ($caja?->ancho_papel ?: 58) === 80 ? 80 : 58;
    }

    /** @param  Collection<int, Articulo>  $articulos */
    public static function texto(Collection $articulos, int $columnas, ?Configuracion $tienda = null): string
    {
        $tienda ??= Configuracion::actual();
        $raya = str_repeat('-', $columnas);
        $centro = fn (string $t) => mb_str_pad(mb_substr($t, 0, $columnas), $columnas, ' ', STR_PAD_BOTH);

        $l = [
            $centro(mb_strtoupper($tienda->nombre_tienda)),
            $centro('EXISTENCIAS'),
            $centro(now()->format('d/m/Y H:i')),
            $raya,
        ];

        foreach ($articulos as $a) {
            foreach (self::envolver(mb_strtoupper($a->descripcion), $columnas) as $renglon) {
                $l[] = $renglon;
            }
            $l[] = mb_substr($a->clave, 0, $columnas);
            $existencia = 'Exist: '.Numero::cantidad($a->existencia).' '.$a->unidad?->abreviatura;
            $l[] = self::par($columnas, $existencia, 'Contado:____');
            $estado = (float) $a->existencia <= 0 ? 'AGOTADO' : ($a->estaBajoMinimo() ? 'BAJO MIN' : '');
            $limites = $a->existencia_minima !== null || $a->existencia_maxima !== null
                ? 'Min: '.($a->existencia_minima !== null ? Numero::cantidad($a->existencia_minima) : '-')
                    .'  Max: '.($a->existencia_maxima !== null ? Numero::cantidad($a->existencia_maxima) : '-')
                : '';
            if ($limites !== '' || $estado !== '') {
                $l[] = trim($limites.'  '.$estado);
            }
            $l[] = $raya;
        }

        $l[] = 'Articulos: '.$articulos->count();
        $l[] = '';
        $l[] = 'Reviso: '.str_repeat('_', max(4, $columnas - 8));
        $l[] = '';
        $l[] = 'Notas:';
        $l[] = '';
        $l[] = str_repeat('_', $columnas);
        $l[] = '';
        $l[] = str_repeat('_', $columnas);

        return implode("\n", $l)."\n";
    }

    /** @return list<string> */
    private static function envolver(string $texto, int $ancho): array
    {
        $renglones = [];
        $actual = '';
        foreach (preg_split('/\s+/u', trim($texto)) as $palabra) {
            while (mb_strlen($palabra) > $ancho) {
                if ($actual !== '') {
                    $renglones[] = $actual;
                    $actual = '';
                }
                $renglones[] = mb_substr($palabra, 0, $ancho);
                $palabra = mb_substr($palabra, $ancho);
            }
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;
            if (mb_strlen($prueba) > $ancho) {
                $renglones[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $renglones[] = $actual;
        }

        return $renglones;
    }

    private static function par(int $ancho, string $izquierda, string $derecha): string
    {
        $espacio = $ancho - mb_strlen($izquierda) - mb_strlen($derecha);

        return $espacio >= 1 ? $izquierda.str_repeat(' ', $espacio).$derecha : $izquierda."\n".str_pad($derecha, $ancho, ' ', STR_PAD_LEFT);
    }
}
