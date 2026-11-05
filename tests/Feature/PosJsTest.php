<?php

/**
 * La pantalla de Venta es un solo objeto de Alpine (`Alpine.data('pos', …)`): si dos propiedades o métodos
 * llevan el mismo nombre, la segunda pisa a la primera y la venta se rompe sin avisar (así falló el escaneo
 * cuando la cola de impresión se llamó igual que la cola de lecturas).
 */
it('el objeto de la pantalla de Venta no repite nombres de propiedades ni de métodos', function () {
    $lineas = file(resource_path('js/pos.js'), FILE_IGNORE_NEW_LINES);
    $inicio = collect($lineas)->search(fn ($l) => str_contains($l, "Alpine.data('pos'"));
    expect($inicio)->not->toBeFalse();

    $nombres = [];
    foreach (array_slice($lineas, $inicio + 1, null, true) as $numero => $linea) {
        // Solo el primer nivel del objeto (8 espacios de sangría): propiedades, getters y métodos.
        if (preg_match('/^ {8}(?:async |get )?([A-Za-z_]\w*)\s*(?::|\()/', $linea, $m) && ! in_array($m[1], ['if', 'for', 'while', 'return', 'switch', 'catch'], true)) {
            $nombres[$m[1]][] = $numero + 1;
        }
    }

    $repetidos = array_filter($nombres, fn ($lineas) => count($lineas) > 1);

    expect($nombres)->not->toBeEmpty()->and($repetidos)->toBe([], 'Nombres repetidos en pos.js: '.json_encode($repetidos));
});
