<?php

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\ListaExistencias;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    Caja::factory()->create(['serie' => 'A', 'ancho_papel' => 58]);
    Configuracion::actual()->update(['nombre_tienda' => 'Miscelánea Emmanuel']);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->leche = Articulo::factory()->create(['descripcion' => 'LECHE ENTERA 1 L', 'clave' => '7501', 'existencia' => '12', 'existencia_minima' => '5', 'existencia_maxima' => '20']);
    $this->jamon = Articulo::factory()->create(['descripcion' => 'JAMON DE PAVO REBANADO EXTRA FINO PAQUETE FAMILIAR', 'clave' => '2002', 'existencia' => '0']);
    $this->inactivo = Articulo::factory()->create(['descripcion' => 'DADO DE BAJA', 'activo' => false]);
});

it('arma la hoja a 32 columnas con existencia, mínimo y raya para anotar', function () {
    $texto = ListaExistencias::texto(ListaExistencias::articulos([$this->leche->id, $this->jamon->id, $this->inactivo->id, 'x']), 32);
    $lineas = explode("\n", rtrim($texto));

    expect($texto)->toContain('MISCELÁNEA EMMANUEL')->toContain('EXISTENCIAS')
        ->toContain('LECHE ENTERA 1 L')->toContain('Contado:____')->toContain('Min: 5  Max: 20')
        ->toContain('AGOTADO')->toContain('Articulos: 2')->not->toContain('DADO DE BAJA')
        ->and(collect($lineas)->every(fn ($l) => mb_strlen($l) <= 32))->toBeTrue();

    // Ordenada por descripción: el jamón antes que la leche, y su nombre largo partido en renglones.
    expect(strpos($texto, 'JAMON'))->toBeLessThan(strpos($texto, 'LECHE'));
});

it('la página y el ESC/POS solo los abre quien ve el inventario', function () {
    $ids = $this->leche->id.','.$this->jamon->id;

    $this->actingAs($this->encargado)->get(route('inventario.existencias', ['ids' => $ids, 'imprimir' => 1]))
        ->assertOk()->assertSee('LECHE ENTERA 1 L')->assertSee('size: 58mm auto', false)->assertSee('window.print()', false);

    $escpos = base64_decode($this->actingAs($this->encargado)->get(route('inventario.existencias-escpos', ['ids' => $ids]))->assertOk()->getContent());
    expect($escpos)->toStartWith("\x1B@")->toContain('LECHE ENTERA 1 L')->toEndWith("\x1DV".chr(66).chr(0));

    $sinPermiso = User::factory()->create();
    $this->actingAs($sinPermiso)->get(route('inventario.existencias-escpos', ['ids' => $ids]))->assertForbidden();
    $this->actingAs($this->encargado)->get(route('inventario.existencias', ['ids' => (string) $this->inactivo->id]))->assertNotFound();
});

it('en Inventario se marcan artículos sin perder la página y se marcan los del filtro', function () {
    Volt::actingAs($this->encargado)->test('pages.inventario.index')
        ->set('seleccion', [(string) $this->leche->id, 'basura'])
        ->assertSet('seleccion', [(string) $this->leche->id])
        ->assertSee('1 artículo marcado')
        ->set('filtro', 'agotados')
        ->call('seleccionarFiltrados')
        ->assertSet('seleccion', [(string) $this->leche->id, (string) $this->jamon->id])
        ->assertSee('Imprimir existencias')
        ->call('quitarSeleccion')
        ->assertSet('seleccion', [])
        ->assertDontSee('Imprimir existencias');
});

it('la tarjeta del filtro elegido se marca como activa', function () {
    Volt::actingAs($this->encargado)->test('pages.inventario.index')
        ->set('filtro', 'agotados')
        ->assertSeeHtml('aria-pressed="true"')
        ->assertSee('Filtrando');
});
