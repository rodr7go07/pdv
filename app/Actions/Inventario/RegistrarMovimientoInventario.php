<?php

namespace App\Actions\Inventario;

use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\MovimientoInventario;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ÚNICA vía para cambiar la existencia de un artículo (decisión técnica 3).
 *
 * Bloquea el artículo, actualiza el acumulado y escribe la fila del kardex en la
 * misma transacción. Los artículos que no llevan inventario no generan movimiento.
 */
class RegistrarMovimientoInventario
{
    /**
     * @param  string  $cantidad  con signo: positiva entra, negativa sale
     */
    public function __invoke(
        int|Articulo $articulo,
        TipoMovimiento $tipo,
        string $cantidad,
        ?string $costoUnitario = null,
        ?User $usuario = null,
        ?Model $referencia = null,
        ?string $motivo = null,
        ?string $nota = null,
    ): ?MovimientoInventario {
        $id = $articulo instanceof Articulo ? $articulo->id : $articulo;

        return DB::transaction(function () use ($id, $tipo, $cantidad, $costoUnitario, $usuario, $referencia, $motivo, $nota) {
            // withTrashed: una cancelación puede afectar a un artículo eliminado después de la venta.
            $fila = Articulo::withTrashed()->lockForUpdate()->findOrFail($id);

            if (! $fila->inventariable) {
                return null;
            }

            $nueva = BigDecimal::of((string) $fila->existencia)->plus($cantidad)->toScale(3);

            $fila->forceFill(['existencia' => (string) $nueva])->save();

            return MovimientoInventario::query()->create([
                'articulo_id' => $fila->id,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'existencia_resultante' => (string) $nueva,
                'referencia_type' => $referencia?->getMorphClass(),
                'referencia_id' => $referencia?->getKey(),
                'motivo' => $motivo,
                'nota' => $nota,
                'user_id' => $usuario?->id,
            ]);
        });
    }
}
