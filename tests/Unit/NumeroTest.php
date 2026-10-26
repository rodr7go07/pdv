<?php

use App\Support\Numero;

it('quita los ceros sobrantes de una cantidad decimal', function (mixed $entrada, string $esperado) {
    expect(Numero::cantidad($entrada))->toBe($esperado);
})->with([
    ['10.000', '10'],
    ['12.5000', '12.5'],
    ['0.000', '0'],
    ['-3.500', '-3.5'],
    ['-0.000', '0'],
    ['100', '100'],
    ['1,230', '1,230'],
    [5, '5'],
    [2.5, '2.5'],
    ['', '0'],
    [null, '0'],
]);
