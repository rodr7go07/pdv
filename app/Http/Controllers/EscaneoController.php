<?php

namespace App\Http\Controllers;

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Recibe los códigos leídos con la cámara (o dados con Enter) en pantallas que no consultan al servidor por sí solas. */
class EscaneoController extends Controller
{
    public function __invoke(Request $request, RegistrarEscaneo $registrar): Response
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:'.RegistrarEscaneo::LARGO_MAXIMO],
            'origen' => ['required', Rule::enum(OrigenEscaneo::class)],
            'medio' => ['sometimes', Rule::enum(MedioEscaneo::class)],
        ]);

        $registrar($datos['codigo'], OrigenEscaneo::from($datos['origen']), MedioEscaneo::tryFrom($datos['medio'] ?? '') ?? MedioEscaneo::Camara, $request->user());

        return response()->noContent();
    }
}
