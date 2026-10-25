<?php

namespace App\Actions\Escaneos;

use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Models\Articulo;
use App\Models\Escaneo;
use App\Models\Turno;
use App\Models\User;
use Throwable;

/**
 * Anota un código escaneado en la bitácora.
 *
 * Un código de barras no lleva espacios: lo que sí los lleva es un nombre que alguien escribió en el campo
 * de captura, y eso no se registra. Registrar nunca debe estorbar a quien escanea, así que cualquier
 * falla se reporta y se ignora.
 */
final class RegistrarEscaneo
{
    public const LARGO_MAXIMO = 80;

    public function __invoke(string $codigo, OrigenEscaneo $origen, MedioEscaneo $medio, ?User $usuario): ?Escaneo
    {
        $codigo = trim($codigo);

        if ($codigo === '' || mb_strlen($codigo) > self::LARGO_MAXIMO || preg_match('/\s/u', $codigo)) {
            return null;
        }

        try {
            $articulo = Articulo::porClave($codigo);

            return Escaneo::query()->create([
                'codigo' => $codigo,
                'origen' => $origen,
                'medio' => $medio,
                'user_id' => $usuario?->id,
                'caja_id' => Turno::abiertoDe($usuario)?->caja_id,
                'articulo_id' => $articulo?->id,
                'encontrado' => $articulo !== null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
