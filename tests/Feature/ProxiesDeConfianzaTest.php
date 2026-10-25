<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_esquema', fn () => url('/recurso'))->middleware('web');
    TrustProxies::flushState();
});

afterEach(fn () => TrustProxies::flushState());

function cabecerasDeTunel(): array
{
    return ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'tienda.ngrok-free.app', 'X-Forwarded-Port' => '443'];
}

it('sin proxies de confianza ignora las cabeceras reenviadas', function () {
    $url = $this->get('/_esquema', cabecerasDeTunel())->getContent();

    expect($url)->toStartWith('http://')->not->toContain('ngrok');
});

it('en producción ignora el comodín * (se podría falsear la IP del límite de acceso)', function () {
    app()['env'] = 'production';
    config(['app.trusted_proxies' => '*']);
    (new AppServiceProvider(app()))->boot();

    expect($this->get('/_esquema', cabecerasDeTunel())->getContent())->toStartWith('http://')->not->toContain('ngrok');
});

it('en producción sí acepta una lista de IP concretas', function () {
    app()['env'] = 'production';
    config(['app.trusted_proxies' => '127.0.0.1, 10.0.0.5']);
    (new AppServiceProvider(app()))->boot();

    expect($this->get('/_esquema', cabecerasDeTunel())->getContent())->toBe('https://tienda.ngrok-free.app/recurso');
});

it('con proxies de confianza genera las URLs en https', function () {
    config(['app.trusted_proxies' => '*']);
    (new AppServiceProvider(app()))->boot();

    expect($this->get('/_esquema', cabecerasDeTunel())->getContent())->toBe('https://tienda.ngrok-free.app/recurso');
});
