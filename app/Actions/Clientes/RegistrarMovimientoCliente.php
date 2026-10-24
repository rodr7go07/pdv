<?php

namespace App\Actions\Clientes;

use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\User;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ÚNICA vía para cambiar el saldo de un cliente: bloquea al cliente, actualiza el saldo
 * y guarda el movimiento con el saldo que quedó, todo en la misma transacción.
 *
 * Cargo (compra a crédito) suma a la deuda; abono, cancelación y devolución la reducen.
 */
class RegistrarMovimientoCliente
{
    public function __invoke(
        int|Cliente $cliente,
        string $tipo,
        string $monto,
        ?User $usuario = null,
        ?Venta $venta = null,
        ?int $turnoId = null,
        ?string $forma = null,
        ?string $referencia = null,
        ?string $nota = null,
    ): ClienteMovimiento {
        $id = $cliente instanceof Cliente ? $cliente->id : $cliente;
        $monto = BigDecimal::of($monto)->toScale(2, RoundingMode::HalfUp);

        if (! in_array($tipo, ['cargo', 'abono', 'cancelacion', 'devolucion'], true) || ! $monto->isPositive()) {
            throw ValidationException::withMessages(['monto' => 'El movimiento de la cuenta no es válido.']);
        }

        return DB::transaction(function () use ($id, $tipo, $monto, $usuario, $venta, $turnoId, $forma, $referencia, $nota) {
            $c = Cliente::withTrashed()->lockForUpdate()->findOrFail($id);

            $nuevo = $tipo === 'cargo'
                ? BigDecimal::of((string) $c->saldo)->plus($monto)
                : BigDecimal::of((string) $c->saldo)->minus($monto);

            if ($nuevo->isNegative()) {
                throw ValidationException::withMessages(['monto' => 'El monto es mayor que lo que el cliente debe ($'.$c->saldo.').']);
            }

            $c->forceFill(['saldo' => (string) $nuevo])->save();

            return ClienteMovimiento::query()->create([
                'cliente_id' => $c->id,
                'tipo' => $tipo,
                'monto' => (string) $monto,
                'saldo_resultante' => (string) $nuevo,
                'venta_id' => $venta?->id,
                'turno_id' => $turnoId,
                'forma' => $forma,
                'referencia' => $referencia,
                'nota' => $nota,
                'user_id' => $usuario?->id,
            ]);
        });
    }
}
