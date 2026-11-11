<?php

/*
 * La pantalla de Venta la maneja Alpine: los métodos que llama con $wire solo devuelven datos. Sin #[Renderless],
 * cada llamada (abrir el cobro, cobrar, buscar cliente…) vuelve a pintar toda la vista en el servidor y la manda
 * completa al navegador, y la caja se siente lenta.
 */
it('los métodos que la caja llama con $wire no vuelven a pintar la pantalla', function () {
    $codigo = file_get_contents(resource_path('views/livewire/pages/pos.blade.php'));

    preg_match_all('/((?:#\[\w+\]\s*)*)public function (\w+)\(/', $codigo, $m, PREG_SET_ORDER);

    $faltan = collect($m)
        // mount y los #[Computed] (configuracionJs se usa al pintar) no se llaman desde el navegador como acciones.
        ->reject(fn ($f) => in_array($f[2], ['mount', 'configuracionJs'], true) || str_contains($f[1], '#[Computed]'))
        ->reject(fn ($f) => str_contains($f[1], '#[Renderless]'))
        ->map(fn ($f) => $f[2])
        ->values()
        ->all();

    expect($faltan)->toBe([], 'Agrega #[Renderless] a: '.implode(', ', $faltan));
});
