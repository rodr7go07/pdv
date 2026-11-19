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
    expect(file_get_contents(base_path("deploy/scripts/{$script}.sh")))->not->toContain("\r");
})->with(['provision', 'desplegar', 'respaldo', 'restaurar', 'salud'])->group('despliegue');

it('la plantilla de producción apaga el modo de depuración y exige cookies seguras', function () {
    $env = file_get_contents(base_path('deploy/.env.production.example'));

    expect($env)->toContain('APP_ENV=production')->toContain('APP_DEBUG=false')->toContain('SESSION_SECURE_COOKIE=true');
});

it('nginx bloquea archivos ocultos y guarda los archivos compilados', function () {
    $conf = file_get_contents(base_path('deploy/nginx/punto-de-venta.conf'));

    expect($conf)->toContain('deny all')->toContain('location /build/')->toContain('client_max_body_size')
        ->toContain('Strict-Transport-Security');
});

it('sube el límite de PHP para el Excel de productos aunque el servidor tenga 2 MB', function () {
    $ini = file_get_contents(public_path('.user.ini'));

    expect($ini)->toContain('upload_max_filesize = 10M')->toContain('post_max_size = 12M')
        ->and(file_get_contents(public_path('.htaccess')))->toContain('php_value upload_max_filesize 10M');
});

it('el botón Importar no se queda apagado después de subir el archivo', function () {
    // wire:loading.attr con la subida como objetivo regresa el botón al estado de antes (apagado, sin archivo).
    $vista = file_get_contents(resource_path('views/livewire/pages/articulos/importar.blade.php'));

    expect($vista)->toContain('wire:target="importar"')->not->toContain('wire:target="importar,archivo"');
});

it('desplegar no pone el sitio en mantenimiento y no borra los archivos compilados anteriores', function () {
    // Decisión del dueño: la tienda sigue cobrando mientras se actualiza.
    $script = file_get_contents(base_path('deploy/scripts/desplegar.sh'));

    expect($script)->not->toContain('artisan down')->not->toContain('artisan up')
        ->toContain('npm run build -- --emptyOutDir=false');
});
