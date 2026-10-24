<?php

namespace App\Actions\Configuracion;

use App\Models\Configuracion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActualizarConfiguracion
{
    /**
     * @param  array{nombre_tienda: string, razon_social?: ?string, rfc?: ?string, direccion?: ?string, telefono?: ?string, leyenda_ticket?: ?string}  $datos
     */
    public function __invoke(Configuracion $configuracion, array $datos, ?UploadedFile $logo = null, bool $quitarLogo = false): Configuracion
    {
        if (isset($datos['rfc'])) {
            $datos['rfc'] = mb_strtoupper($datos['rfc']);
        }

        $logoAnterior = $configuracion->logo_ruta;
        $logoNuevo = null;

        if ($logo) {
            $logoNuevo = $logo->store('logos', 'public');
            $datos['logo_ruta'] = $logoNuevo;
        } elseif ($quitarLogo) {
            $datos['logo_ruta'] = null;
        }

        try {
            DB::transaction(fn () => $configuracion->update($datos));
        } catch (\Throwable $e) {
            if ($logoNuevo) {
                Storage::disk('public')->delete($logoNuevo);
            }

            throw $e;
        }

        if ($logoAnterior && array_key_exists('logo_ruta', $datos) && $datos['logo_ruta'] !== $logoAnterior) {
            Storage::disk('public')->delete($logoAnterior);
        }

        return $configuracion->refresh();
    }
}
