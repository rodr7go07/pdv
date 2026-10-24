<?php

namespace App\Actions\Inventario;

use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Conteo;
use App\Models\ConteoPartida;
use App\Models\User;
use App\Services\CalculadoraPrecios;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conteo físico: se captura lo contado, se revisan las diferencias y se aplica.
 *
 * Al aplicar se ajusta la DIFERENCIA contra lo que decía el sistema cuando se contó
 * (no contra la existencia de hoy), así las ventas hechas mientras se contaba no se pierden.
 */
class GestionarConteo
{
    public function __construct(private readonly RegistrarMovimientoInventario $movimiento) {}

    public function crear(string $nombre, User $usuario): Conteo
    {
        $nombre = trim($nombre);

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw ValidationException::withMessages(['nombre' => 'Escribe un nombre de hasta 100 caracteres para el conteo.']);
        }

        return Conteo::query()->create(['nombre' => $nombre, 'user_id' => $usuario->id, 'estado' => 'en_proceso']);
    }

    /** Captura (o corrige) lo contado de un artículo. Guarda la existencia del sistema en ese momento. */
    public function capturar(Conteo $conteo, Articulo $articulo, string $cantidad): ConteoPartida
    {
        if (! is_numeric($cantidad) || BigDecimal::of($cantidad)->isNegative()) {
            throw ValidationException::withMessages(['cantidad' => 'Escribe una cantidad válida (0 o más).']);
        }

        if (! $articulo->unidad->permite_decimales && CalculadoraPrecios::tieneDecimales($cantidad)) {
            throw ValidationException::withMessages(['cantidad' => 'Este artículo solo admite cantidades enteras.']);
        }

        if (! $articulo->inventariable) {
            throw ValidationException::withMessages(['clave' => 'Este artículo no lleva control de inventario.']);
        }

        return DB::transaction(function () use ($conteo, $articulo, $cantidad) {
            $conteo = Conteo::query()->lockForUpdate()->findOrFail($conteo->id);

            if (! $conteo->enProceso()) {
                throw ValidationException::withMessages(['conteo' => 'Este conteo ya no está en proceso.']);
            }

            $partida = $conteo->partidas()->where('articulo_id', $articulo->id)->first();

            $existencia = Articulo::query()->whereKey($articulo->id)->value('existencia');

            // Al recapturar, la cuenta nueva se compara con la existencia de ahora, no con la de la primera captura.
            if ($partida) {
                $partida->update(['cantidad_contada' => $cantidad, 'existencia_sistema' => $existencia]);

                return $partida;
            }

            return $conteo->partidas()->create([
                'articulo_id' => $articulo->id,
                'existencia_sistema' => $existencia,
                'cantidad_contada' => $cantidad,
            ]);
        });
    }

    public function quitar(Conteo $conteo, int $partidaId): void
    {
        abort_unless($conteo->enProceso(), 422, 'Este conteo ya no está en proceso.');

        $conteo->partidas()->whereKey($partidaId)->delete();
    }

    /** @return array{ajustados: int, sin_diferencia: int} */
    public function aplicar(Conteo $conteo, User $usuario): array
    {
        return DB::transaction(function () use ($conteo, $usuario) {
            $conteo = Conteo::query()->lockForUpdate()->findOrFail($conteo->id);

            if (! $conteo->enProceso()) {
                throw ValidationException::withMessages(['conteo' => 'Este conteo ya fue aplicado o cancelado.']);
            }

            $ajustados = 0;
            $sinDiferencia = 0;

            foreach ($conteo->partidas()->orderBy('articulo_id')->get() as $partida) {
                $diferencia = BigDecimal::of((string) $partida->cantidad_contada)->minus((string) $partida->existencia_sistema);

                if ($diferencia->isZero()) {
                    $sinDiferencia++;

                    continue;
                }

                ($this->movimiento)(
                    $partida->articulo_id,
                    TipoMovimiento::Conteo,
                    (string) $diferencia->toScale(3),
                    usuario: $usuario,
                    referencia: $conteo,
                    motivo: 'conteo',
                    nota: $conteo->nombre,
                );

                $ajustados++;
            }

            $conteo->update(['estado' => 'aplicado', 'aplicado_at' => now()]);

            return ['ajustados' => $ajustados, 'sin_diferencia' => $sinDiferencia];
        });
    }

    public function cancelar(Conteo $conteo): void
    {
        abort_unless($conteo->enProceso(), 422, 'Este conteo ya no está en proceso.');

        $conteo->update(['estado' => 'cancelado']);
    }
}
