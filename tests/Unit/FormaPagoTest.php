<?php

use App\Enums\FormaPago;

it('lista las formas de pago y las que entran dinero en el momento', function () {
    expect(FormaPago::valores())->toBe(['efectivo', 'tarjeta', 'transferencia', 'credito'])
        ->and(FormaPago::cobradas())->toBe(['efectivo', 'tarjeta', 'transferencia']);
});

it('da la etiqueta de un valor guardado, aunque la forma ya no exista', function () {
    expect(FormaPago::etiquetaDe('credito'))->toBe('Crédito')
        ->and(FormaPago::etiquetaDe('cheque'))->toBe('cheque')
        ->and(FormaPago::etiquetaDe(null))->toBe('')
        ->and(FormaPago::Credito->etiquetaCorte())->toBe('A crédito (sin cobrar)')
        ->and(FormaPago::Efectivo->etiquetaCorte())->toBe('Efectivo');
});
