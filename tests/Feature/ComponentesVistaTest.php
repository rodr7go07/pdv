<?php

use Illuminate\Support\Facades\Blade;

it('pinta la tarjeta de indicador con su cifra, nota y tono', function () {
    $html = Blade::render('<x-kpi etiqueta="Bajo el mínimo" tono="aviso" tamano="grande">12<x-slot:nota class="mt-0.5">A costo</x-slot:nota></x-kpi>');

    expect($html)
        ->toContain('Bajo el mínimo')
        ->toContain('>12<')
        ->toContain('A costo')
        ->toContain('border-etiqueta')
        ->toContain('text-3xl')
        // Diseño plano (decisión 77): sin la orilla de sombra de color debajo de la tarjeta.
        ->not->toContain('shadow-[');
});

it('la tarjeta de indicador puede ser un botón', function () {
    $html = Blade::render('<x-kpi tag="button" type="button" etiqueta="Agotados">3</x-kpi>');

    expect($html)
        ->toStartWith('<button')
        ->toContain('type="button"');
});

it('los botones y tarjetas son planos: sin sombra de tecla ni brillos de color (decisión 77)', function () {
    $faltan = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $archivo) {
        if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (file($archivo->getPathname()) as $n => $linea) {
            if (preg_match('/shadow-\[0_\d+px_0_0_var\(--color-|active:translate-y-/', $linea)) {
                $faltan[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname()).':'.($n + 1);
            }
        }
    }

    expect($faltan)->toBe([], 'Quita la sombra de tecla en: '.implode(', ', $faltan));
});

it('pinta los tres tonos de la alerta', function () {
    expect(Blade::render('<x-alerta>Listo</x-alerta>'))->toContain('emerald')->toContain('Listo')
        ->and(Blade::render('<x-alerta tono="error">Falló</x-alerta>'))->toContain('role="alert"')->toContain('red')
        ->and(Blade::render('<x-alerta tono="aviso">Ojo</x-alerta>'))->toContain('amber');
});

it('el enlace de volver conserva la ruta y navega sin recargar', function () {
    $html = Blade::render('<x-enlace-volver href="/ventas">Volver a ventas</x-enlace-volver>');

    expect($html)->toContain('href="/ventas"')->toContain('wire:navigate')->toContain('Volver a ventas');
});
