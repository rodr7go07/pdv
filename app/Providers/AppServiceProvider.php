<?php

namespace App\Providers;

use App\Http\Middleware\UsuarioActivo;
use Illuminate\Auth\Middleware\Authorize;
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
        // Las peticiones de Livewire también revisan que el usuario siga activo y conserve los permisos de la ruta.
        Livewire::addPersistentMiddleware([UsuarioActivo::class, Authorize::class, PermissionMiddleware::class]);
    }
}
