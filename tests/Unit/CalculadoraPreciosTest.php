<?php

use App\Services\CalculadoraPrecios as Calc;

it('suma tasas de varios impuestos', function () {
    expect(Calc::sumarTasas(['16', '8']))->toBe('24.0000')
        ->and(Calc::sumarTasas([]))->toBe('0.0000')
        ->and(Calc::sumarTasas(['16.5', '0']))->toBe('16.5000');
});

it('quita los impuestos de un precio con impuestos incluidos', function () {
    expect(Calc::precioSinImpuestos('116', '16'))->toBe('100.0000')
        ->and(Calc::precioSinImpuestos('15', '0'))->toBe('15.0000')
        ->and(Calc::precioSinImpuestos('10', '16'))->toBe('8.6207');
});

it('calcula el precio de venta desde el costo y el margen', function () {
    // 100 de costo + 30% de margen = 130 sin IVA; con IVA 16% = 150.80
    expect(Calc::precioDesdeMargen('100', '30', '16'))->toBe('150.80')
        ->and(Calc::precioDesdeMargen('10', '50', '0'))->toBe('15.00')
        ->and(Calc::precioDesdeMargen('0', '50', '16'))->toBe('0.00');
});

it('calcula el margen desde el precio con impuestos', function () {
    expect(Calc::margenDesdePrecio('100', '150.80', '16'))->toBe('30.00')
        ->and(Calc::margenDesdePrecio('10', '15', '0'))->toBe('50.00')
        ->and(Calc::margenDesdePrecio('10', '8', '0'))->toBe('-20.00');
});

it('no calcula margen cuando no hay costo', function () {
    expect(Calc::margenDesdePrecio('0', '15', '16'))->toBeNull();
});

it('redondea a centavos hacia arriba en el punto medio', function () {
    // 0.5% de margen sobre 10.10 => 10.1505 => 10.15 (sin impuestos)
    expect(Calc::precioDesdeMargen('10.10', '0.5', '0'))->toBe('10.15')
        ->and(Calc::precioDesdeMargen('1.005', '0', '0'))->toBe('1.01');
});

it('tolera valores vacíos o inválidos como cero', function () {
    expect(Calc::precioDesdeMargen('', 'abc', ''))->toBe('0.00');
});
