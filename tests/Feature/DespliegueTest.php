<?php

it('expone la ruta de salud para el monitoreo', function () {
    $this->get('/up')->assertOk();
});

it('usa la hora de México en la app y en la conexión MySQL', function () {
    expect(config('app.timezone'))->toBe('America/Mexico_City')
        ->and(config('database.connections.mysql.timezone'))->toBe('-06:00');
});

it('trae todos los archivos de despliegue', function (string $archivo) {
    expect(base_path($archivo))->toBeFile();
})->with([
    'deploy/README.md',
    'deploy/.env.production.example',
    'deploy/nginx/punto-de-venta.conf',
    'deploy/supervisor/punto-de-venta-worker.conf',
    'deploy/cron/punto-de-venta',
    'deploy/scripts/provision.sh',
    'deploy/scripts/desplegar.sh',
    'deploy/scripts/respaldo.sh',
    'deploy/scripts/restaurar.sh',
    'deploy/scripts/salud.sh',
]);

it('los scripts no tienen finales de línea de Windows (romperían en Linux)', function (string $script) {
    expect(file_get_contents(base_path($script)))->not->toContain("\r");
})->with(['provision', 'desplegar', 'respaldo', 'restaurar', 'salud'])->group('despliegue');

it('la plantilla de producción apaga el modo de depuración y exige cookies seguras', function () {
    $env = file_get_contents(base_path('deploy/.env.production.example'));

    expect($env)->toContain('APP_ENV=production')->toContain('APP_DEBUG=false')->toContain('SESSION_SECURE_COOKIE=true');
});

it('nginx bloquea archivos ocultos y guarda los archivos compilados', function () {
    $conf = file_get_contents(base_path('deploy/nginx/punto-de-venta.conf'));

    expect($conf)->toContain('deny all')->toContain('location /build/')->toContain('client_max_body_size');
});
