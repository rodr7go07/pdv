<?php

namespace App\Actions\Caja;

use App\Models\MovimientoCaja;
use App\Models\Turno;
use App\Models\User;
use App\Services\ResumenTurno;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entrada o retiro de efectivo del cajón, siempre con motivo. */
class RegistrarMovimientoCaja
{
    /**
     * @param  'entrada'|'retiro'  $tipo
     *
     * @throws ValidationException
     */
    public function __invoke(Turno $turno, string $tipo, string $monto, string $motivo, User $usuario): MovimientoCaja
    {
        abort_unless($usuario->can('caja.retiro'), 403, 'No tienes permiso para registrar entradas o retiros de efectivo.');
        abort_unless($turno->user_id === $usuario->id || $usuario->can('caja.supervisar'), 403, 'Este turno es de otro cajero.');

        $motivo = trim($motivo);

        if (! in_array($tipo, ['entrada', 'retiro'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Elige si es una entrada o un retiro.']);
        }

        if (! is_numeric($monto) || BigDecimal::of($monto)->isLessThanOrEqualTo(0) || BigDecimal::of($monto)->isGreaterThan('99999999')) {
            throw ValidationException::withMessages(['monto' => 'Escribe un monto mayor que cero.']);
        }

        if ($motivo === '' || mb_strlen($motivo) > 255) {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo (máximo 255 caracteres).']);
        }

        return DB::transaction(function () use ($turno, $tipo, $monto, $motivo, $usuario) {
            $turno = Turno::query()->lockForUpdate()->findOrFail($turno->id);

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['turno' => 'Este turno ya está cerrado.']);
            }

            $monto = BigDecimal::of($monto)->toScale(2, RoundingMode::HalfUp);

            if ($tipo === 'retiro') {
                $disponible = BigDecimal::of(ResumenTurno::calcular($turno)['efectivo_esperado']);

                if ($monto->isGreaterThan($disponible)) {
                    throw ValidationException::withMessages(['monto' => 'No hay tanto efectivo en la caja. Disponible: $'.$disponible.'.']);
                }
            }

            return MovimientoCaja::query()->create([
                'turno_id' => $turno->id,
                'tipo' => $tipo,
                'monto' => (string) $monto,
                'motivo' => $motivo,
                'user_id' => $usuario->id,
            ]);
        });
    }
}
