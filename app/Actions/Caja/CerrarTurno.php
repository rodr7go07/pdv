<?php

namespace App\Actions\Caja;

use App\Models\Corte;
use App\Models\Turno;
use App\Models\User;
use App\Services\ResumenTurno;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cortes de caja.
 *  - Corte X (parcial): foto de las cifras sin cerrar el turno.
 *  - Corte Z (final): se captura el efectivo contado (arqueo), se calcula el sobrante o faltante,
 *    se guarda la foto y el turno queda cerrado: ya no se puede vender en él.
 */
class CerrarTurno
{
    public function corteX(Turno $turno, User $usuario): Corte
    {
        $this->autorizar($turno, $usuario);

        return DB::transaction(function () use ($turno, $usuario) {
            $turno = Turno::query()->lockForUpdate()->findOrFail($turno->id);

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['turno' => 'Este turno ya está cerrado.']);
            }

            return Corte::query()->create([
                'turno_id' => $turno->id,
                'tipo' => 'X',
                'user_id' => $usuario->id,
                'datos' => $this->datos($turno),
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function cerrar(Turno $turno, string $efectivoContado, User $usuario, ?string $notas = null): Corte
    {
        $this->autorizar($turno, $usuario);

        if (! is_numeric($efectivoContado) || BigDecimal::of($efectivoContado)->isNegative() || BigDecimal::of($efectivoContado)->isGreaterThan('99999999')) {
            throw ValidationException::withMessages(['efectivo_contado' => 'Escribe el efectivo que contaste (0 o más).']);
        }

        $notas = $notas !== null ? mb_substr(trim($notas), 0, 255) : null;

        return DB::transaction(function () use ($turno, $efectivoContado, $usuario, $notas) {
            $turno = Turno::query()->lockForUpdate()->findOrFail($turno->id);

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['turno' => 'Este turno ya está cerrado.']);
            }

            $resumen = ResumenTurno::calcular($turno);
            $contado = BigDecimal::of($efectivoContado)->toScale(2, RoundingMode::HalfUp);
            $diferencia = $contado->minus($resumen['efectivo_esperado']);

            $turno->update([
                'estado' => 'cerrado',
                'cerrado_at' => now(),
                'cerrado_por' => $usuario->id,
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'efectivo_contado' => (string) $contado,
                'diferencia' => (string) $diferencia,
                'notas' => $notas ?: null,
                'caja_abierta_id' => null,
                'usuario_abierto_id' => null,
            ]);

            return Corte::query()->create([
                'turno_id' => $turno->id,
                'tipo' => 'Z',
                'user_id' => $usuario->id,
                'datos' => $this->datos($turno->refresh()) + [
                    'efectivo_contado' => (string) $contado,
                    'diferencia' => (string) $diferencia,
                    'notas' => $notas,
                ],
            ]);
        });
    }

    private function autorizar(Turno $turno, User $usuario): void
    {
        abort_unless($usuario->can('caja.corte'), 403, 'No tienes permiso para hacer cortes de caja.');
        abort_unless($turno->user_id === $usuario->id || $usuario->can('caja.supervisar'), 403, 'Este turno es de otro cajero.');
    }

    /** @return array<string, mixed> */
    private function datos(Turno $turno): array
    {
        $turno->loadMissing(['caja', 'usuario']);

        return ResumenTurno::calcular($turno) + [
            'caja' => $turno->caja->nombre,
            'cajero' => $turno->usuario->name,
            'abierto_at' => $turno->abierto_at->toIso8601String(),
        ];
    }
}
