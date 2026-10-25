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
 * Solo se anota lo que parece un código: sin espacios, en UTF-8 válido y, si no coincide con ningún artículo,
 * con al menos un dígito (los códigos de barras y las claves llevan números; «leche» o «pan» escritos en el campo
 * de captura son nombres y no deben salir como productos por dar de alta). Registrar nunca debe estorbar a quien
 * escanea, así que cualquier falla se reporta y se ignora.
 */
final class RegistrarEscaneo
{
    public const LARGO_MAXIMO = 80;

    public function __invoke(string $codigo, OrigenEscaneo $origen, MedioEscaneo $medio, ?User $usuario): ?Escaneo
    {
        $codigo = trim($codigo);

        if ($codigo === '' || ! mb_check_encoding($codigo, 'UTF-8') || mb_strlen($codigo) > self::LARGO_MAXIMO || preg_match('/\s/u', $codigo)) {
            return null;
        }

        try {
            $articulo = Articulo::porClave($codigo);

            if (! $articulo && ! preg_match('/\d/', $codigo)) {
                return null;
            }

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
