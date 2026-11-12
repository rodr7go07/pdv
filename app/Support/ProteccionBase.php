<?php

namespace App\Support;

/**
 * Candado contra comandos que borran toda la base (migrate:fresh, migrate:refresh, migrate:reset, db:wipe).
 *
 * Se bloquean en producción y en cualquier base que no sea SQLite (la MySQL de la tienda o la de Laragon con datos
 * de prueba). Las pruebas y la prueba de humo usan SQLite, así que siguen funcionando. Para borrar a propósito una
 * MySQL local: `PERMITIR_BORRAR_BASE=true` en el .env, correr el comando y volver a quitarlo.
 *
 * Existe porque el 12/11/2026 un `migrate:fresh` pensado para una base temporal llegó a la MySQL local: con la
 * configuración en caché (`config:cache`) Laravel ignora las variables de entorno del comando.
 */
final class ProteccionBase
{
    public static function bloquear(): bool
    {
        if (app()->isProduction()) {
            return true;
        }

        if ((bool) config('database.permitir_borrar')) {
            return false;
        }

        return config('database.connections.'.config('database.default').'.driver') !== 'sqlite';
    }
}
