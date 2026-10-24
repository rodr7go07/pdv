<?php

namespace App\Actions\Caja;

use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre el turno de un cajero en una caja con su fondo inicial.
 * La base de datos garantiza un solo turno abierto por caja y por usuario.
 */
class AbrirTurno
{
    public function __invoke(Caja $caja, User $usuario, string $fondoInicial): Turno
    {
        abort_unless($usuario->can('caja.abrir'), 403, 'No tienes permiso para abrir turnos de caja.');

        $fondoInicial = trim($fondoInicial) === '' ? '0' : trim($fondoInicial);

        if (! is_numeric($fondoInicial) || BigDecimal::of($fondoInicial)->isNegative() || BigDecimal::of($fondoInicial)->isGreaterThan('99999999')) {
            throw ValidationException::withMessages(['fondo_inicial' => 'Escribe un fondo inicial válido (0 o más).']);
        }

        try {
            return DB::transaction(function () use ($caja, $usuario, $fondoInicial) {
                $caja = Caja::query()->lockForUpdate()->find($caja->id);

                if (! $caja || ! $caja->activa) {
                    throw ValidationException::withMessages(['caja' => 'Esta caja no está disponible.']);
                }

                if (Turno::query()->abiertos()->where('user_id', $usuario->id)->exists()) {
                    throw ValidationException::withMessages(['caja' => 'Ya tienes un turno abierto. Ciérralo antes de abrir otro.']);
                }

                if (Turno::query()->abiertos()->where('caja_id', $caja->id)->exists()) {
                    throw ValidationException::withMessages(['caja' => 'Esa caja ya tiene un turno abierto con otro cajero.']);
                }

                return Turno::query()->create([
                    'caja_id' => $caja->id,
                    'user_id' => $usuario->id,
                    'estado' => 'abierto',
                    'fondo_inicial' => (string) BigDecimal::of($fondoInicial)->toScale(2, RoundingMode::HalfUp),
                    'abierto_at' => now(),
                    'caja_abierta_id' => $caja->id,
                    'usuario_abierto_id' => $usuario->id,
                ]);
            });
        } catch (QueryException $e) {
            // Dos aperturas simultáneas: gana una y la otra recibe este aviso.
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                throw ValidationException::withMessages(['caja' => 'Esa caja o este usuario ya tiene un turno abierto.']);
            }

            throw $e;
        }
    }
}
