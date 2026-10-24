<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\TipoMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela una venta (no se borra): regresa el inventario y deja motivo, usuario y fecha.
 * Una venta con devoluciones ya no se puede cancelar.
 */
class CancelarVenta
{
    public function __construct(private readonly RegistrarMovimientoInventario $movimiento) {}

    public function __invoke(Venta $venta, string $motivo, User $usuario): Venta
    {
        abort_unless($usuario->can('ventas.cancelar'), 403, 'No tienes permiso para cancelar ventas.');

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la cancelación.']);
        }

        if (mb_strlen($motivo) > 255) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es demasiado largo (máximo 255 caracteres).']);
        }

        return DB::transaction(function () use ($venta, $motivo, $usuario) {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ($venta->estaCancelada()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya estaba cancelada.']);
            }

            if ($venta->devoluciones()->exists()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya tiene devoluciones y no se puede cancelar.']);
            }

            // Una venta de un turno ya cortado no se cancela (cambiaría un corte cerrado): se usa una devolución.
            if ($venta->turno_id) {
                $turno = Turno::query()->lockForUpdate()->find($venta->turno_id);

                if (! $turno?->estaAbierto()) {
                    throw ValidationException::withMessages(['venta' => 'El turno de esta venta ya se cerró. Registra una devolución en lugar de cancelarla.']);
                }
            }

            foreach ($venta->partidas as $partida) {
                ($this->movimiento)(
                    $partida->articulo_id,
                    TipoMovimiento::CancelacionVenta,
                    (string) BigDecimal::of((string) $partida->cantidad),
                    (string) $partida->costo_unitario,
                    $usuario,
                    $venta,
                    nota: $motivo,
                );
            }

            $venta->update([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
                'cancelada_por' => $usuario->id,
                'motivo_cancelacion' => $motivo,
            ]);

            return $venta->load(['partidas', 'pagos']);
        });
    }
}
