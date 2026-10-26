<?php

namespace App\Actions\Clientes;

use App\Enums\FormaPago;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Turno;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Abono a la deuda de un cliente. Si se paga en efectivo el dinero entra al cajón,
 * por eso se exige un turno abierto de quien lo recibe (y aparece en su corte).
 */
class RegistrarAbono
{
    public function __construct(private readonly RegistrarMovimientoCliente $movimiento) {}

    /**
     * @param  'efectivo'|'tarjeta'|'transferencia'  $forma
     *
     * @throws ValidationException
     */
    public function __invoke(Cliente $cliente, string $monto, string $forma, ?string $referencia, User $usuario): ClienteMovimiento
    {
        abort_unless($usuario->can('clientes.abonar'), 403, 'No tienes permiso para registrar abonos.');

        validator(
            ['monto' => $monto, 'forma' => $forma, 'referencia' => $referencia],
            [
                'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
                'forma' => ['required', Rule::in(FormaPago::cobradas())],
                'referencia' => ['nullable', 'string', 'max:60'],
            ],
            ['monto.gt' => 'El abono debe ser mayor que cero.', 'monto.required' => 'Escribe el monto del abono.'],
        )->validate();

        return DB::transaction(function () use ($cliente, $monto, $forma, $referencia, $usuario) {
            $turno = Turno::query()->abiertos()->where('user_id', $usuario->id)->lockForUpdate()->first();

            if ($forma === 'efectivo' && ! $turno) {
                throw ValidationException::withMessages(['monto' => 'Para recibir efectivo necesitas tener tu turno de caja abierto.']);
            }

            $c = Cliente::query()->lockForUpdate()->findOrFail($cliente->id);

            if (BigDecimal::of($monto)->isGreaterThan((string) $c->saldo)) {
                throw ValidationException::withMessages(['monto' => 'El abono es mayor que la deuda ($'.$c->saldo.').']);
            }

            return ($this->movimiento)(
                $c, 'abono', $monto, $usuario,
                turnoId: $turno?->id, forma: $forma, referencia: $referencia ?: null,
            );
        });
    }
}
