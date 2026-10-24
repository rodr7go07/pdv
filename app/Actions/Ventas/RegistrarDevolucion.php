<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\TipoMovimiento;
use App\Models\Devolucion;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPartida;
use App\Services\CalculadoraPrecios;
use App\Services\Folios;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devolución parcial o total de partidas de una venta completada.
 * Regresa las piezas al inventario y calcula el reembolso con lo que realmente se cobró
 * (precio con el descuento aplicado); la última pieza absorbe el redondeo.
 */
class RegistrarDevolucion
{
    public function __construct(private readonly RegistrarMovimientoInventario $movimiento) {}

    /**
     * @param  list<array{partida_id: int|string, cantidad: string}>  $items
     *
     * @throws ValidationException
     */
    public function __invoke(Venta $venta, array $items, string $motivo, User $usuario): Devolucion
    {
        abort_unless($usuario->can('ventas.devolver'), 403, 'No tienes permiso para registrar devoluciones.');

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) > 255) {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la devolución (máximo 255 caracteres).']);
        }

        $items = collect($items)->filter(fn ($i) => isset($i['cantidad']) && is_numeric($i['cantidad']) && (float) $i['cantidad'] > 0)->values()->all();

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Indica cuántas piezas se devuelven.']);
        }

        return DB::transaction(function () use ($venta, $items, $motivo, $usuario) {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ($venta->estaCancelada()) {
                throw ValidationException::withMessages(['venta' => 'No se puede devolver una venta cancelada.']);
            }

            // El dinero sale del cajón de quien procesa la devolución; si no tiene turno, del turno aún abierto de la venta.
            $turno = Turno::query()->abiertos()->where('user_id', $usuario->id)->lockForUpdate()->first()
                ?? ($venta->turno_id ? Turno::query()->abiertos()->lockForUpdate()->find($venta->turno_id) : null);

            if (! $turno) {
                throw ValidationException::withMessages(['venta' => 'Necesitas un turno de caja abierto para devolver dinero.']);
            }

            $partidas = VentaPartida::query()->where('venta_id', $venta->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $total = BigDecimal::zero();
            $renglones = [];

            foreach ($items as $i => $item) {
                $partida = $partidas[(int) $item['partida_id']] ?? null;

                if (! $partida) {
                    throw ValidationException::withMessages(["items.$i" => 'Esa partida no pertenece a la venta.']);
                }

                $cantidad = BigDecimal::of(trim((string) $item['cantidad']))->toScale(3, RoundingMode::HalfUp);
                $disponible = BigDecimal::of($partida->cantidadDevolvible());

                if ($cantidad->isZero() || $cantidad->isGreaterThan($disponible)) {
                    throw ValidationException::withMessages(["items.$i" => "De «{$partida->descripcion}» solo se pueden devolver {$disponible} {$partida->unidad}."]);
                }

                if (CalculadoraPrecios::tieneDecimales((string) $item['cantidad']) && ! $partida->articulo->unidad->permite_decimales) {
                    throw ValidationException::withMessages(["items.$i" => "«{$partida->descripcion}» solo admite piezas completas."]);
                }

                $monto = $cantidad->isEqualTo($disponible)
                    ? BigDecimal::of((string) $partida->total)->minus((string) $partida->monto_devuelto)
                    : BigDecimal::of((string) $partida->total)->multipliedBy($cantidad)->dividedBy((string) $partida->cantidad, 2, RoundingMode::HalfUp);

                $renglones[] = ['partida' => $partida, 'cantidad' => $cantidad, 'monto' => $monto->toScale(2, RoundingMode::HalfUp)];
                $total = $total->plus($monto);
            }

            $devolucion = Devolucion::query()->create([
                'folio' => Folios::formato('D', Folios::siguiente('D')),
                'venta_id' => $venta->id,
                'turno_id' => $turno->id,
                'user_id' => $usuario->id,
                'motivo' => $motivo,
                'total' => (string) $total->toScale(2),
                'forma' => 'efectivo',
            ]);

            foreach ($renglones as $r) {
                /** @var VentaPartida $partida */
                $partida = $r['partida'];

                $devolucion->partidas()->create([
                    'venta_partida_id' => $partida->id,
                    'articulo_id' => $partida->articulo_id,
                    'cantidad' => (string) $r['cantidad'],
                    'monto' => (string) $r['monto'],
                ]);

                $partida->update([
                    'cantidad_devuelta' => (string) BigDecimal::of((string) $partida->cantidad_devuelta)->plus($r['cantidad']),
                    'monto_devuelto' => (string) BigDecimal::of((string) $partida->monto_devuelto)->plus($r['monto']),
                ]);

                ($this->movimiento)(
                    $partida->articulo_id,
                    TipoMovimiento::Devolucion,
                    (string) $r['cantidad'],
                    (string) $partida->costo_unitario,
                    $usuario,
                    $devolucion,
                    nota: $motivo,
                );
            }

            return $devolucion->load('partidas');
        });
    }
}
