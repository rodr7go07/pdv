<?php

namespace App\Actions\Compras;

use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\TipoMovimiento;
use App\Models\Compra;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela una compra (no se borra): regresa las existencias y deja el motivo.
 * Los costos no se revierten; el siguiente movimiento de compra los recalcula.
 */
class CancelarCompra
{
    public function __construct(private readonly RegistrarMovimientoInventario $movimiento) {}

    public function __invoke(Compra $compra, string $motivo, User $usuario): Compra
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la cancelación.']);
        }

        return DB::transaction(function () use ($compra, $motivo, $usuario) {
            $compra = Compra::query()->lockForUpdate()->findOrFail($compra->id);

            if ($compra->estaCancelada()) {
                throw ValidationException::withMessages(['compra' => 'Esta compra ya estaba cancelada.']);
            }

            foreach ($compra->partidas as $partida) {
                ($this->movimiento)(
                    $partida->articulo_id,
                    TipoMovimiento::CancelacionCompra,
                    (string) BigDecimal::of((string) $partida->cantidad)->negated(),
                    (string) $partida->costo_unitario,
                    $usuario,
                    $compra,
                    nota: $motivo,
                );
            }

            $compra->update([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
                'cancelada_por' => $usuario->id,
                'motivo_cancelacion' => $motivo,
            ]);

            return $compra;
        });
    }
}
