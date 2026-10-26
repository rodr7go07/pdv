<?php

namespace App\Services;

use App\Enums\FormaPago;
use App\Support\Fecha;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cifras para reportes y tablero. Reglas:
 *  - Solo cuentan ventas completadas (las canceladas no suman).
 *  - Utilidad bruta = precio de venta sin impuestos − costo guardado en la partida (la "foto" del momento).
 *  - Las devoluciones se restan en el periodo de la venta original (con su costo y sus impuestos proporcionales).
 *  - El departamento es el actual del artículo (la partida no guarda departamento).
 */
final class ReporteVentas
{
    public CarbonImmutable $desde;

    public CarbonImmutable $hasta;

    public function __construct(
        CarbonImmutable|string $desde,
        CarbonImmutable|string $hasta,
        public ?int $cajaId = null,
        public ?int $userId = null,
    ) {
        $this->desde = Fecha::inicio($desde) ?? CarbonImmutable::today();
        $this->hasta = Fecha::fin($hasta) ?? CarbonImmutable::today()->endOfDay();
    }

    /** Partidas de ventas completadas del periodo y filtros. */
    private function partidas(): Builder
    {
        return DB::table('venta_partidas as p')
            ->join('ventas as v', 'v.id', '=', 'p.venta_id')
            ->where('v.estado', 'completada')
            ->whereBetween('v.created_at', [$this->desde, $this->hasta])
            ->when($this->cajaId, fn ($q) => $q->where('v.caja_id', $this->cajaId))
            ->when($this->userId, fn ($q) => $q->where('v.user_id', $this->userId));
    }

    private function ventas(): Builder
    {
        return DB::table('ventas as v')
            ->where('v.estado', 'completada')
            ->whereBetween('v.created_at', [$this->desde, $this->hasta])
            ->when($this->cajaId, fn ($q) => $q->where('v.caja_id', $this->cajaId))
            ->when($this->userId, fn ($q) => $q->where('v.user_id', $this->userId));
    }

    /** Columnas agregadas de partidas (se suman por grupo). */
    private const AGREGADOS = 'SUM(p.cantidad) as cantidad,
        SUM(p.total) as importe,
        SUM(p.subtotal) as subtotal,
        SUM(p.cantidad * p.costo_unitario) as costo,
        SUM(p.monto_devuelto) as devuelto,
        SUM(p.monto_devuelto / (1 + p.tasa_impuestos / 100)) as devuelto_subtotal,
        SUM(p.cantidad_devuelta * p.costo_unitario) as devuelto_costo';

    /**
     * Resumen general del periodo.
     *
     * @return array{ventas: int, bruto: string, devuelto: string, neto: string, descuento: string, impuestos: string, costo: string, utilidad: string, margen: ?string, ticket_promedio: string, canceladas: int}
     */
    public function resumen(): array
    {
        $enc = $this->ventas()->selectRaw('COUNT(*) as n, COALESCE(SUM(v.total),0) as total, COALESCE(SUM(v.descuento),0) as descuento, COALESCE(SUM(v.impuestos),0) as impuestos')->first();
        $lin = $this->partidas()->selectRaw(self::AGREGADOS)->first();

        $canceladas = DB::table('ventas as v')->where('v.estado', 'cancelada')
            ->whereBetween('v.created_at', [$this->desde, $this->hasta])
            ->when($this->cajaId, fn ($q) => $q->where('v.caja_id', $this->cajaId))
            ->when($this->userId, fn ($q) => $q->where('v.user_id', $this->userId))
            ->count();

        $fila = $this->calcular($lin);
        $bruto = $this->dec($enc->total);
        $ventas = (int) $enc->n;

        return [
            'ventas' => $ventas,
            'bruto' => (string) $bruto,
            'devuelto' => $fila['devuelto'],
            'neto' => $fila['neto'],
            'descuento' => (string) $this->dec($enc->descuento),
            'impuestos' => (string) $this->dec($enc->impuestos),
            'costo' => $fila['costo'],
            'utilidad' => $fila['utilidad'],
            'margen' => $fila['margen'],
            'ticket_promedio' => $ventas > 0 ? (string) $bruto->dividedBy($ventas, 2, RoundingMode::HalfUp) : '0.00',
            'canceladas' => $canceladas,
        ];
    }

    /**
     * Ventas agrupadas por día, semana (lunes) o mes.
     *
     * @param  'dia'|'semana'|'mes'  $agrupar
     * @return Collection<int, array<string, mixed>>
     */
    public function porPeriodo(string $agrupar = 'dia'): Collection
    {
        $dia = 'DATE(v.created_at)';

        $filas = $this->partidas()
            ->selectRaw("$dia as dia, COUNT(DISTINCT v.id) as ventas, ".self::AGREGADOS)
            ->groupBy(DB::raw($dia))->orderBy('dia')->get();

        $grupos = $filas->groupBy(fn ($f) => match ($agrupar) {
            'semana' => CarbonImmutable::parse($f->dia)->startOfWeek()->toDateString(),
            'mes' => CarbonImmutable::parse($f->dia)->startOfMonth()->toDateString(),
            default => $f->dia,
        });

        return $grupos->map(function (Collection $g, string $inicio) use ($agrupar) {
            $suma = fn (string $c) => $g->reduce(fn (BigDecimal $a, $f) => $a->plus((string) ($f->{$c} ?? 0)), BigDecimal::zero());
            $fila = $this->calcular((object) [
                'cantidad' => $suma('cantidad'), 'importe' => $suma('importe'), 'subtotal' => $suma('subtotal'), 'costo' => $suma('costo'),
                'devuelto' => $suma('devuelto'), 'devuelto_subtotal' => $suma('devuelto_subtotal'), 'devuelto_costo' => $suma('devuelto_costo'),
            ]);

            return ['periodo' => $inicio, 'etiqueta' => $this->etiquetaPeriodo($inicio, $agrupar), 'ventas' => (int) $g->sum('ventas')] + $fila;
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function porDepartamento(): Collection
    {
        return $this->partidas()
            ->join('articulos as a', 'a.id', '=', 'p.articulo_id')
            ->join('departamentos as d', 'd.id', '=', 'a.departamento_id')
            ->selectRaw('d.id as id, d.nombre as nombre, '.self::AGREGADOS)
            ->groupBy('d.id', 'd.nombre')->orderByRaw('SUM(p.total) DESC')->get()
            ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre] + $this->calcular($f));
    }

    /**
     * @param  'importe'|'cantidad'|'utilidad'  $orden
     * @return Collection<int, array<string, mixed>>
     */
    public function porArticulo(int $limite = 50, string $orden = 'importe'): Collection
    {
        $filas = $this->partidas()
            ->selectRaw('p.articulo_id as id, MAX(p.clave) as clave, MAX(p.descripcion) as nombre, MAX(p.unidad) as unidad, '.self::AGREGADOS)
            ->groupBy('p.articulo_id')->get()
            ->map(fn ($f) => ['id' => $f->id, 'clave' => $f->clave, 'nombre' => $f->nombre, 'unidad' => $f->unidad] + $this->calcular($f));

        $clave = in_array($orden, ['importe', 'cantidad', 'utilidad'], true) ? $orden : 'importe';

        return $filas->sortByDesc(fn ($f) => (float) ($clave === 'importe' ? $f['neto'] : $f[$clave]))->take($limite)->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function porCajero(): Collection
    {
        return $this->partidas()
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->selectRaw('u.id as id, u.name as nombre, COUNT(DISTINCT v.id) as ventas, '.self::AGREGADOS)
            ->groupBy('u.id', 'u.name')->orderByRaw('SUM(p.total) DESC')->get()
            ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre, 'ventas' => (int) $f->ventas] + $this->calcular($f));
    }

    /**
     * Cobrado por forma de pago (efectivo aplicado, sin el cambio).
     *
     * @return Collection<int, array{forma: string, etiqueta: string, ventas: int, monto: string}>
     */
    public function porFormaPago(): Collection
    {
        return DB::table('venta_pagos as g')
            ->join('ventas as v', 'v.id', '=', 'g.venta_id')
            ->where('v.estado', 'completada')
            ->whereBetween('v.created_at', [$this->desde, $this->hasta])
            ->when($this->cajaId, fn ($q) => $q->where('v.caja_id', $this->cajaId))
            ->when($this->userId, fn ($q) => $q->where('v.user_id', $this->userId))
            ->groupBy('g.forma')
            ->selectRaw('g.forma as forma, COUNT(DISTINCT g.venta_id) as ventas, SUM(g.monto) as monto')
            ->get()
            ->map(fn ($f) => ['forma' => $f->forma, 'etiqueta' => FormaPago::etiquetaDe($f->forma), 'ventas' => (int) $f->ventas, 'monto' => (string) $this->dec($f->monto)])
            ->sortByDesc(fn ($f) => (float) $f['monto'])->values();
    }

    /**
     * Valor del inventario actual por departamento, a costo vigente y a precio de venta.
     *
     * @return array{filas: Collection<int, array<string, mixed>>, costo: string, venta: string, articulos: int}
     */
    public static function valorInventario(): array
    {
        $filas = DB::table('articulos as a')
            ->join('departamentos as d', 'd.id', '=', 'a.departamento_id')
            ->whereNull('a.deleted_at')->where('a.inventariable', true)->where('a.existencia', '>', 0)
            ->selectRaw('d.id as id, d.nombre as nombre, COUNT(*) as articulos, SUM(a.existencia) as piezas, SUM(a.existencia * a.costo) as costo, SUM(a.existencia * a.precio_1) as venta')
            ->groupBy('d.id', 'd.nombre')->orderByRaw('SUM(a.existencia * a.costo) DESC')->get();

        $dec = fn ($v) => (string) BigDecimal::of((string) ($v ?? 0))->toScale(2, RoundingMode::HalfUp);

        return [
            'filas' => $filas->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre, 'articulos' => (int) $f->articulos, 'piezas' => (string) BigDecimal::of((string) $f->piezas)->toScale(3, RoundingMode::HalfUp), 'costo' => $dec($f->costo), 'venta' => $dec($f->venta)]),
            'costo' => $dec($filas->sum('costo')),
            'venta' => $dec($filas->sum('venta')),
            'articulos' => (int) $filas->sum('articulos'),
        ];
    }

    /** Neto, costo y utilidad de una fila agregada. */
    private function calcular(object $f): array
    {
        $importe = $this->dec($f->importe);
        $devuelto = $this->dec($f->devuelto);
        $subtotalNeto = $this->dec($f->subtotal)->minus($this->dec($f->devuelto_subtotal));
        $costoNeto = $this->dec($f->costo)->minus($this->dec($f->devuelto_costo));
        $utilidad = $subtotalNeto->minus($costoNeto);

        return [
            'cantidad' => (string) BigDecimal::of((string) ($f->cantidad ?? 0))->toScale(3, RoundingMode::HalfUp),
            'bruto' => (string) $importe,
            'devuelto' => (string) $devuelto,
            'neto' => (string) $importe->minus($devuelto),
            'costo' => (string) $costoNeto->toScale(2, RoundingMode::HalfUp),
            'utilidad' => (string) $utilidad->toScale(2, RoundingMode::HalfUp),
            'margen' => $subtotalNeto->isPositive() ? (string) $utilidad->multipliedBy(100)->dividedBy($subtotalNeto, 1, RoundingMode::HalfUp) : null,
        ];
    }

    private function dec(mixed $v): BigDecimal
    {
        return BigDecimal::of((string) ($v ?? 0))->toScale(2, RoundingMode::HalfUp);
    }

    private function etiquetaPeriodo(string $inicio, string $agrupar): string
    {
        $f = CarbonImmutable::parse($inicio);

        return match ($agrupar) {
            'semana' => 'Semana del '.$f->translatedFormat('j M').' al '.$f->endOfWeek()->translatedFormat('j M'),
            'mes' => ucfirst($f->translatedFormat('F Y')),
            default => ucfirst($f->translatedFormat('D j M')),
        };
    }
}
