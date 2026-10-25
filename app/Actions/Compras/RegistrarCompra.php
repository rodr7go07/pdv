<?php

namespace App\Actions\Compras;

use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\MetodoCosteo;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Configuracion;
use App\Models\HistorialPrecio;
use App\Models\User;
use App\Services\Folios;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registra una compra: guarda el documento, sube la existencia y actualiza los costos.
 *
 * Costos: en cada compra se guardan el ÚLTIMO costo y el costo PROMEDIO ponderado.
 * Según el método elegido en Configuración, uno de los dos pasa a ser el costo vigente
 * del artículo (el que se usa para calcular márgenes y utilidad).
 */
class RegistrarCompra
{
    /** Máximo de partidas por compra. */
    public const MAXIMO_PARTIDAS = 500;

    public function __construct(private readonly RegistrarMovimientoInventario $movimiento) {}

    /**
     * @param  array{proveedor_id: int|string, factura?: ?string, fecha: string, notas?: ?string}  $datos
     * @param  list<array{articulo_id: int|string, cantidad: string, costo_unitario: string}>  $partidas
     *
     * @throws ValidationException
     */
    public function __invoke(array $datos, array $partidas, User $usuario): Compra
    {
        $this->validar($datos, $partidas);

        $metodo = Configuracion::actual()->metodo_costeo ?? MetodoCosteo::Promedio;

        return DB::transaction(function () use ($datos, $partidas, $usuario, $metodo) {
            $compra = Compra::query()->create([
                'folio' => Folios::formato('C', Folios::siguiente('C')),
                'proveedor_id' => $datos['proveedor_id'],
                'factura' => ($datos['factura'] ?? null) ?: null,
                'fecha' => $datos['fecha'],
                'notas' => ($datos['notas'] ?? null) ?: null,
                'total' => 0,
                'estado' => 'registrada',
                'user_id' => $usuario->id,
            ]);

            $total = BigDecimal::zero();

            // Se consolidan renglones repetidos del mismo artículo en uno solo.
            foreach ($this->consolidar($partidas) as $p) {
                $articulo = Articulo::query()->lockForUpdate()->findOrFail($p['articulo_id']);
                $cantidad = BigDecimal::of($p['cantidad']);
                $costo = BigDecimal::of($p['costo_unitario'])->toScale(4, RoundingMode::HalfUp);
                $importe = $cantidad->multipliedBy($costo)->toScale(2, RoundingMode::HalfUp);

                $compra->partidas()->create([
                    'articulo_id' => $articulo->id,
                    'clave' => $articulo->clave,
                    'descripcion' => $articulo->descripcion,
                    'cantidad' => (string) $cantidad,
                    'costo_unitario' => (string) $costo,
                    'total' => (string) $importe,
                ]);

                $this->actualizarCostos($articulo, $cantidad, $costo, $metodo, $usuario);

                ($this->movimiento)($articulo, TipoMovimiento::Compra, (string) $cantidad, (string) $costo, $usuario, $compra);

                $total = $total->plus($importe);
            }

            $compra->update(['total' => (string) $total]);

            return $compra->load('partidas');
        });
    }

    private function actualizarCostos(Articulo $articulo, BigDecimal $cantidad, BigDecimal $costo, MetodoCosteo $metodo, User $usuario): void
    {
        // Una existencia negativa o cero no pesa en el promedio.
        $existencia = BigDecimal::of((string) $articulo->existencia);
        $base = $existencia->isPositive() ? $existencia : BigDecimal::zero();

        $promedio = $base->isZero()
            ? $costo
            : $base->multipliedBy((string) $articulo->costo_promedio)->plus($cantidad->multipliedBy($costo))
                ->dividedBy($base->plus($cantidad), 4, RoundingMode::HalfUp);

        $vigente = $metodo === MetodoCosteo::Ultimo ? $costo : $promedio;
        $costoAnterior = (string) $articulo->costo;

        $articulo->forceFill([
            'ultimo_costo' => (string) $costo,
            'costo_promedio' => (string) $promedio,
            'costo' => (string) $vigente,
        ])->save();

        if (! BigDecimal::of($costoAnterior)->isEqualTo($vigente)) {
            HistorialPrecio::query()->create([
                'articulo_id' => $articulo->id,
                'user_id' => $usuario->id,
                'campo' => 'costo',
                'valor_anterior' => $costoAnterior,
                'valor_nuevo' => (string) $vigente,
                'origen' => 'compra',
            ]);
        }
    }

    /**
     * @param  list<array{articulo_id: int|string, cantidad: string, costo_unitario: string}>  $partidas
     * @return list<array{articulo_id: int, cantidad: string, costo_unitario: string}>
     */
    private function consolidar(array $partidas): array
    {
        $consolidado = [];

        foreach ($partidas as $p) {
            $id = (int) $p['articulo_id'];
            $cantidad = BigDecimal::of((string) $p['cantidad']);
            $costo = BigDecimal::of((string) $p['costo_unitario']);

            if (! isset($consolidado[$id])) {
                $consolidado[$id] = ['cantidad' => $cantidad, 'importe' => $cantidad->multipliedBy($costo)];

                continue;
            }

            $consolidado[$id]['cantidad'] = $consolidado[$id]['cantidad']->plus($cantidad);
            $consolidado[$id]['importe'] = $consolidado[$id]['importe']->plus($cantidad->multipliedBy($costo));
        }

        // Orden fijo por artículo: evita bloqueos cruzados entre dos compras simultáneas.
        return collect($consolidado)->sortKeys()->map(fn ($c, $id) => [
            'articulo_id' => $id,
            'cantidad' => (string) $c['cantidad'],
            // costo promedio ponderado de los renglones repetidos
            'costo_unitario' => (string) $c['importe']->dividedBy($c['cantidad'], 4, RoundingMode::HalfUp),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $partidas
     *
     * @throws ValidationException
     */
    private function validar(array $datos, array $partidas): void
    {
        Validator::make(
            $datos + ['partidas' => $partidas],
            [
                'proveedor_id' => ['required', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
                'factura' => ['nullable', 'string', 'max:60'],
                'fecha' => ['required', 'date', 'before_or_equal:today'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'partidas' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_PARTIDAS],
                'partidas.*.articulo_id' => ['required', Rule::exists('articulos', 'id')->whereNull('deleted_at')],
                'partidas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
                'partidas.*.costo_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
            ],
            [
                'partidas.required' => 'Agrega al menos un artículo a la compra.',
                'partidas.min' => 'Agrega al menos un artículo a la compra.',
                'partidas.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
                'fecha.before_or_equal' => 'La fecha de la compra no puede ser futura.',
            ],
            ['proveedor_id' => 'proveedor'],
        )->validate();
    }
}
