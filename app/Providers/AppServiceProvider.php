<?php

namespace App\Providers;

use App\Http\Middleware\UsuarioActivo;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Detrás de un túnel (ngrok) el HTTPS lo termina el proxy: sin esto las URLs de CSS y JS salen con http://.
        if (filled(config('app.trusted_proxies'))) {
            $proxies = trim((string) config('app.trusted_proxies'));

            // Con '*' cualquiera podría falsear X-Forwarded-For y evitar el límite de intentos de acceso (que usa la IP):
            // en producción solo se aceptan IP concretas.
            if ($proxies === '*' && $this->app->isProduction()) {
                logger()->warning("TRUSTED_PROXIES='*' se ignora en producción: indica la lista de IP de tu proxy.");
            } else {
                TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
            }
        }

        // Las peticiones de Livewire también revisan que el usuario siga activo y conserve los permisos de la ruta.
        Livewire::addPersistentMiddleware([UsuarioActivo::class, Authorize::class, PermissionMiddleware::class]);
    }
}
