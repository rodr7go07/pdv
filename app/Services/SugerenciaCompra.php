<?php

namespace App\Services;

use App\Models\Articulo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qué comprar y cuánto, con base en lo vendido y en los mínimos del artículo.
 *
 *  - Promedio diario = (vendido − devuelto) de ventas completadas en los últimos N días ÷ N.
 *  - Objetivo = lo que cubre los días de cobertura, nunca menos que el mínimo y, si hay máximo, sin pasarlo.
 *  - Sugerido = objetivo − existencia, redondeado hacia arriba (a enteros salvo artículos a granel).
 *  - Solo se incluyen artículos activos con inventario y sugerido mayor a cero.
 */
final class SugerenciaCompra
{
    public const DIAS_VENTA = [7, 15, 30, 60, 90];

    public const COBERTURA_MAXIMA = 90;

    public readonly int $diasVenta;

    public readonly int $diasCobertura;

    /** @var ?Collection<int, BigDecimal> */
    private ?Collection $vendidoCache = null;

    /** Valor de `$proveedor` para los artículos que no tienen proveedor principal. */
    public const SIN_PROVEEDOR = 'sin';

    /**
     * @param  int|string|null  $proveedor  id de un proveedor, `SIN_PROVEEDOR` o null (todos)
     */
    public function __construct(int $diasVenta = 30, int $diasCobertura = 15, public readonly ?int $departamentoId = null, public readonly int|string|null $proveedor = null)
    {
        $this->diasVenta = in_array($diasVenta, self::DIAS_VENTA, true) ? $diasVenta : 30;
        $this->diasCobertura = max(1, min(self::COBERTURA_MAXIMA, $diasCobertura));
    }

    /**
     * @return Collection<int, array{id: int, clave: string, descripcion: string, unidad: string, proveedor: ?string, decimales: bool, existencia: string, minimo: ?string, vendido: string, promedio: string, dias_restantes: ?string, sugerido: string, costo: string, importe: string, estado: string}>
     */
    public function filas(): Collection
    {
        $vendido = $this->vendido();

        $articulos = Articulo::query()
            ->with(['unidad:id,abreviatura,permite_decimales', 'proveedor:id,nombre'])
            ->activos()
            ->where('inventariable', true)
            ->when($this->departamentoId, fn ($q, $id) => $q->where('departamento_id', $id))
            ->when($this->proveedor === self::SIN_PROVEEDOR, fn ($q) => $q->whereNull('proveedor_id'))
            ->when(is_int($this->proveedor), fn ($q) => $q->where('proveedor_id', $this->proveedor))
            ->where(fn ($q) => $q->whereIn('id', $vendido->keys()->all())->orWhere(fn ($w) => $w->bajoMinimo()))
            ->get();

        return $articulos
            ->map(fn (Articulo $a) => $this->fila($a, $vendido->get($a->id, BigDecimal::zero())))
            ->filter()
            ->sortBy(fn (array $f) => [['agotado' => 0, 'bajo_minimo' => 1, 'cobertura' => 2][$f['estado']], (float) ($f['dias_restantes'] ?? 9999), $f['descripcion']])
            ->values();
    }

    /**
     * Fila de un artículo que la persona agregó a mano (no salió del cálculo): mismas cifras, con la cantidad que ella puso.
     *
     * @return array<string, mixed>
     */
    public function filaManual(Articulo $a, string $cantidad = '1'): array
    {
        $a->loadMissing('unidad:id,abreviatura,permite_decimales', 'proveedor:id,nombre');
        $fila = $this->fila($a, $this->vendido()->get($a->id, BigDecimal::zero()), forzar: true);
        $fila['estado'] = 'manual';

        return $this->conCantidad($fila, $cantidad);
    }

    /**
     * La misma fila con otra cantidad a comprar (y su costo estimado).
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public function conCantidad(array $fila, string $cantidad): array
    {
        $fila['sugerido'] = $cantidad;
        $fila['importe'] = (string) BigDecimal::of($cantidad)->multipliedBy($fila['costo'])->toScale(2, RoundingMode::HalfUp);

        return $fila;
    }

    /** @return Collection<int, BigDecimal> cantidad neta vendida por artículo en el periodo */
    private function vendido(): Collection
    {
        return $this->vendidoCache ??= $this->calcularVendido();
    }

    /** @return Collection<int, BigDecimal> */
    private function calcularVendido(): Collection
    {
        $desde = CarbonImmutable::today()->subDays($this->diasVenta - 1);
        $hasta = CarbonImmutable::now();

        $vendidas = DB::table('venta_partidas as p')
            ->join('ventas as v', 'v.id', '=', 'p.venta_id')
            ->where('v.estado', 'completada')
            ->where('v.created_at', '>=', $desde)->where('v.created_at', '<=', $hasta)
            ->groupBy('p.articulo_id')
            ->selectRaw('p.articulo_id as id, SUM(p.cantidad) as cantidad')
            ->pluck('cantidad', 'id');

        $devueltas = DB::table('devolucion_partidas as d')
            ->join('venta_partidas as p', 'p.id', '=', 'd.venta_partida_id')
            ->join('ventas as v', 'v.id', '=', 'p.venta_id')
            ->where('v.estado', 'completada')
            ->where('v.created_at', '>=', $desde)->where('v.created_at', '<=', $hasta)
            ->groupBy('d.articulo_id')
            ->selectRaw('d.articulo_id as id, SUM(d.cantidad) as cantidad')
            ->pluck('cantidad', 'id');

        return $vendidas->map(function ($cantidad, $id) use ($devueltas) {
            $neto = BigDecimal::of($cantidad)->minus($devueltas->get($id, '0'));

            return $neto->isNegative() ? BigDecimal::zero() : $neto;
        });
    }

    private function fila(Articulo $a, BigDecimal $vendido, bool $forzar = false): ?array
    {
        $existencia = BigDecimal::of($a->existencia);
        $minimo = $a->existencia_minima !== null ? BigDecimal::of($a->existencia_minima) : null;
        $promedio = $vendido->dividedBy($this->diasVenta, 6, RoundingMode::HalfUp);

        // Se divide al final para no arrastrar el redondeo del promedio (20 en 30 días × 15 = 10 exactos).
        $objetivo = $vendido->multipliedBy($this->diasCobertura)->dividedBy($this->diasVenta, 6, RoundingMode::HalfUp);
        if ($a->existencia_maxima !== null && BigDecimal::of($a->existencia_maxima)->isPositive()) {
            $objetivo = BigDecimal::min($objetivo, BigDecimal::of($a->existencia_maxima));
        }
        if ($minimo !== null) {
            $objetivo = BigDecimal::max($objetivo, $minimo);
        }

        $faltante = $objetivo->minus($existencia);
        if (! $faltante->isPositive() && ! $forzar) {
            return null;
        }

        // Un artículo agregado a mano puede no hacer falta según el cálculo: la cantidad la pone la persona.
        $faltante = BigDecimal::max($faltante, BigDecimal::zero());

        $sugerido = $faltante->toScale($a->granel ? 3 : 0, RoundingMode::Up);
        $costo = BigDecimal::of($a->ultimo_costo ?? 0)->isPositive() ? BigDecimal::of($a->ultimo_costo) : BigDecimal::of($a->costo);

        return [
            'id' => $a->id,
            'clave' => $a->clave,
            'descripcion' => $a->descripcion,
            'unidad' => $a->unidad->abreviatura,
            'proveedor' => $a->proveedor?->nombre,
            'decimales' => $a->granel || $a->unidad->permite_decimales,
            'existencia' => (string) $existencia->toScale(3),
            'minimo' => $minimo?->toScale(3)->__toString(),
            'vendido' => (string) $vendido->toScale(3),
            'promedio' => (string) $promedio->toScale(2, RoundingMode::HalfUp),
            'dias_restantes' => $promedio->isPositive() ? (string) BigDecimal::max($existencia, BigDecimal::zero())->dividedBy($promedio, 1, RoundingMode::Down) : null,
            'sugerido' => (string) $sugerido,
            'costo' => (string) $costo->toScale(4),
            'importe' => (string) $sugerido->multipliedBy($costo)->toScale(2, RoundingMode::HalfUp),
            'estado' => ! $existencia->isPositive() ? 'agotado' : ($a->estaBajoMinimo() ? 'bajo_minimo' : 'cobertura'),
        ];
    }
}
