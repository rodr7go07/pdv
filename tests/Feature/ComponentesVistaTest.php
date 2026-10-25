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
        ->toContain('shadow-[0_2px_0_0_var(--color-anil-100)]');
});

it('la tarjeta de indicador puede ser un botón y quitar la sombra', function () {
    $html = Blade::render('<x-kpi tag="button" type="button" etiqueta="Agotados" :sombra="false">3</x-kpi>');

    expect($html)
        ->toStartWith('<button')
        ->toContain('type="button"')
        ->not->toContain('shadow-[0_2px_0_0_var(--color-anil-100)]');
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
