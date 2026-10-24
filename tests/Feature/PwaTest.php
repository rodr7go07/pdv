<?php

it('publica un manifest válido con íconos existentes', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons', 'theme_color'])
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['lang'])->toStartWith('es');

    $tamanos = collect($manifest['icons'])->pluck('sizes')->all();
    expect($tamanos)->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icono) {
        expect(public_path(ltrim($icono['src'], '/')))->toBeFile();
    }
});

it('incluye service worker y página sin conexión', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile()
        ->and(file_get_contents(public_path('sw.js')))->toContain('offline.html')
        ->and(public_path('icons/apple-touch-icon.png'))->toBeFile();
});

it('enlaza el manifest desde las pantallas', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('theme-color', escape: false);
});
