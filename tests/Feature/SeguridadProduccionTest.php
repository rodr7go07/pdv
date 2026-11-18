<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_ip', fn () => request()->ip().'|'.(request()->isSecure() ? 'https' : 'http'))->middleware('web');
    TrustProxies::flushState();
});

afterEach(fn () => TrustProxies::flushState());

it('cada respuesta trae las cabeceras de seguridad', function () {
    $r = $this->get('http://localhost/login');

    $r->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');
    expect($r->headers->has('Strict-Transport-Security'))->toBeFalse(); // por http no se manda
});

it('por https también obliga a usar siempre https', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('con TRUSTED_PROXIES=cloudflare toma la IP real solo cuando la petición viene de Cloudflare', function () {
    app()['env'] = 'production';
    config(['app.trusted_proxies' => 'cloudflare']);
    (new AppServiceProvider(app()))->boot();

    $deCloudflare = $this->withServerVariables(['REMOTE_ADDR' => '172.70.10.20'])
        ->get('http://localhost/_ip', ['X-Forwarded-For' => '189.203.1.50', 'X-Forwarded-Proto' => 'https'])->getContent();
    expect($deCloudflare)->toBe('189.203.1.50|https');

    // Alguien que no es Cloudflare no puede hacerse pasar por otra IP.
    $falsa = $this->withServerVariables(['REMOTE_ADDR' => '45.60.1.1'])
        ->get('http://localhost/_ip', ['X-Forwarded-For' => '1.2.3.4', 'X-Forwarded-Proto' => 'https'])->getContent();
    expect($falsa)->toBe('45.60.1.1|http');
});

it('cloudflare se puede combinar con otras IP de confianza', function () {
    app()['env'] = 'production';
    config(['app.trusted_proxies' => 'cloudflare, 10.0.0.5']);
    (new AppServiceProvider(app()))->boot();

    expect($this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->get('http://localhost/_ip', ['X-Forwarded-For' => '189.203.1.50'])->getContent())
        ->toStartWith('189.203.1.50|');
});
