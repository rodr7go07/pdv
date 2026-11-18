<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas de Laravel, sin depender de cómo esté configurado el servidor web
 * ni de Cloudflare (en producción el Nginx no las mandaba). Si el servidor ya pone alguna, no se duplica.
 *
 * - Nadie puede meter el sistema dentro de otra página para engañar clics (X-Frame-Options).
 * - El navegador no adivina tipos de archivo (X-Content-Type-Options).
 * - La dirección completa no se manda a otros sitios (Referrer-Policy).
 * - Cámara y micrófono solo para el propio sistema; ubicación, a nadie (Permissions-Policy).
 * - Por HTTPS: el navegador recuerda usar siempre HTTPS durante un año (Strict-Transport-Security).
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $cabeceras = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(self), geolocation=()',
        ];

        if ($request->isSecure()) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($cabeceras as $nombre => $valor) {
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }

        return $respuesta;
    }
}
