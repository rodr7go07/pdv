<?php

use App\Services\CalculadoraVenta as Venta;
use Brick\Math\BigDecimal;

function lin(string $precio, string $cantidad = '1', string $tasa = '0', array $extra = []): array
{
    return array_merge([
        'precio' => $precio, 'cantidad' => $cantidad, 'tasa' => $tasa,
        'impuestos' => $tasa === '0' ? [] : [['nombre' => 'IVA '.$tasa.'%', 'tasa' => $tasa]],
    ], $extra);
}

it('calcula una partida simple sin impuestos', function () {
    $r = Venta::calcular([lin('15.50', '3')]);

    expect($r['total'])->toBe('46.50')->and($r['subtotal'])->toBe('46.50')->and($r['impuestos'])->toBe('0.00')->and($r['descuento'])->toBe('0.00');
});

it('desglosa el IVA de un precio con impuestos incluidos', function () {
    $r = Venta::calcular([lin('116', '1', '16')]);

    expect($r['total'])->toBe('116.00')->and($r['subtotal'])->toBe('100.00')->and($r['impuestos'])->toBe('16.00')
        ->and($r['lineas'][0]['impuestos_detalle'])->toBe([['nombre' => 'IVA 16%', 'tasa' => '16', 'monto' => '16.00']]);
});

it('redondea cantidades con decimales a centavos', function () {
    // 0.275 kg × 35.00 = 9.625 → 9.63
    expect(Venta::calcular([lin('35', '0.275')])['total'])->toBe('9.63');
});

it('aplica descuento por porcentaje y por monto en la partida', function () {
    $pct = Venta::calcular([lin('100', '2', '0', ['descuento_tipo' => 'pct', 'descuento_valor' => '10'])]);
    $monto = Venta::calcular([lin('100', '2', '0', ['descuento_tipo' => 'monto', 'descuento_valor' => '35'])]);

    expect($pct['total'])->toBe('180.00')->and($pct['descuento'])->toBe('20.00')
        ->and($monto['total'])->toBe('165.00')->and($monto['descuento'])->toBe('35.00');
});

it('limita el descuento al importe de la partida', function () {
    $r = Venta::calcular([lin('10', '1', '0', ['descuento_tipo' => 'monto', 'descuento_valor' => '500'])]);

    expect($r['total'])->toBe('0.00');
});

it('el descuento general se reparte y la suma cuadra al centavo', function () {
    $r = Venta::calcular([lin('10'), lin('10'), lin('10')], ['tipo' => 'monto', 'valor' => '1']);

    $suma = array_sum(array_map('floatval', array_column($r['lineas'], 'descuento')));
    expect($r['total'])->toBe('29.00')->and($r['descuento'])->toBe('1.00')->and(round($suma, 2))->toBe(1.0)
        ->and(array_column($r['lineas'], 'descuento'))->toBe(['0.33', '0.33', '0.34']);
});

it('descuento general por porcentaje sobre el total ya descontado por partida', function () {
    $r = Venta::calcular(
        [lin('100', '1', '0', ['descuento_tipo' => 'pct', 'descuento_valor' => '10']), lin('50')],
        ['tipo' => 'pct', 'valor' => '10'],
    );

    // 90 + 50 = 140 → 10% = 14.00 → 126.00
    expect($r['total'])->toBe('126.00')->and($r['descuento'])->toBe('24.00');
});

it('los importes siempre cuadran: subtotal + impuestos = total', function (array $lineas, array $global) {
    $r = Venta::calcular($lineas, $global);

    $suma = (string) BigDecimal::of($r['subtotal'])->plus($r['impuestos'])->toScale(2);
    expect($suma)->toBe($r['total']);

    foreach ($r['lineas'] as $l) {
        expect((string) BigDecimal::of($l['subtotal'])->plus($l['impuestos'])->toScale(2))->toBe($l['total']);
    }
})->with([
    'tres partidas con IVA' => [[lin('19.99', '3', '16'), lin('7.35', '2', '16'), lin('1.11', '7', '16')], []],
    'iva e ieps' => [[lin('108', '1', '24', ['impuestos' => [['nombre' => 'IVA 16%', 'tasa' => '16'], ['nombre' => 'IEPS 8%', 'tasa' => '8']]])], []],
    'con descuento general' => [[lin('33.33', '3', '16'), lin('12.5', '1.5', '0')], ['tipo' => 'pct', 'valor' => '7.5']],
]);

it('reparte el impuesto entre IVA e IEPS en proporción a su tasa', function () {
    $r = Venta::calcular([lin('124', '1', '24', ['impuestos' => [['nombre' => 'IVA 16%', 'tasa' => '16'], ['nombre' => 'IEPS 8%', 'tasa' => '8']]])]);

    expect($r['impuestos'])->toBe('24.00')
        ->and($r['lineas'][0]['impuestos_detalle'])->toBe([
            ['nombre' => 'IVA 16%', 'tasa' => '16', 'monto' => '16.00'],
            ['nombre' => 'IEPS 8%', 'tasa' => '8', 'monto' => '8.00'],
        ]);
});

it('una venta sin partidas da total cero', function () {
    expect(Venta::calcular([])['total'])->toBe('0.00');
});

it('reparte el descuento general en centavos exactos sin dejar descuentos negativos', function () {
    // Tres partidas iguales y un solo centavo de descuento: lo recibe una sola, las otras quedan en 0.00
    $r = Venta::calcular([lin('1'), lin('1'), lin('1')], ['tipo' => 'monto', 'valor' => '0.01']);
    $descuentos = array_column($r['lineas'], 'descuento');

    expect($r['descuento'])->toBe('0.01')->and($r['total'])->toBe('2.99')
        ->and(array_map('floatval', $descuentos))->each->toBeGreaterThanOrEqual(0);

    // Repartos que redondean hacia arriba en todas las partidas: la suma sigue siendo exacta
    $r = Venta::calcular([lin('0.10'), lin('0.10'), lin('0.10'), lin('0.10')], ['tipo' => 'monto', 'valor' => '0.05']);
    expect($r['descuento'])->toBe('0.05')->and($r['total'])->toBe('0.35');
    foreach ($r['lineas'] as $l) {
        expect((float) $l['descuento'])->toBeGreaterThanOrEqual(0)->and((float) $l['total'])->toBeGreaterThanOrEqual(0);
    }

    // Descuento total: todo a 0 sin partidas negativas
    $r = Venta::calcular([lin('0.03'), lin('0.03'), lin('0.03')], ['tipo' => 'pct', 'valor' => '100']);
    expect($r['total'])->toBe('0.00');
});
