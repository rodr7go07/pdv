<?php

namespace App\Http\Controllers;

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Recibe los códigos leídos con la cámara en pantallas que no consultan al servidor por sí solas. */
class EscaneoController extends Controller
{
    public function __invoke(Request $request, RegistrarEscaneo $registrar): Response
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:'.RegistrarEscaneo::LARGO_MAXIMO],
            'origen' => ['required', Rule::enum(OrigenEscaneo::class)],
        ]);

        $registrar($datos['codigo'], OrigenEscaneo::from($datos['origen']), MedioEscaneo::Camara, $request->user());

        return response()->noContent();
    }
}
