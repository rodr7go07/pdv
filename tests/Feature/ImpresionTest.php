<?php

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->caja = Caja::factory()->create(['nombre' => 'Caja de prueba', 'ancho_papel' => 58]);
});

it('imprime una hoja de prueba con el ancho de la caja', function () {
    $this->actingAs($this->admin)->get("/cajas/{$this->caja->id}/prueba")
        ->assertOk()
        ->assertSee('PRUEBA DE IMPRESIÓN')
        ->assertSee('Caja de prueba')
        ->assertSee('58mm', false)
        ->assertSee('12345678901234567890123456789012')   // 32 caracteres en papel de 58 mm
        ->assertDontSee('123456789012345678901234567890123');
});

it('la hoja de prueba de 80 mm tiene 48 caracteres', function () {
    $this->caja->update(['ancho_papel' => 80]);

    $this->actingAs($this->admin)->get("/cajas/{$this->caja->id}/prueba")->assertSee('80mm', false)->assertSee(substr(str_repeat('1234567890', 5), 0, 48));
});

it('la hoja de prueba solo la ve quien administra cajas', function () {
    $this->actingAs($this->cajero)->get("/cajas/{$this->caja->id}/prueba")->assertForbidden();
});

it('los visitantes no ven la hoja de prueba', function () {
    $this->get("/cajas/{$this->caja->id}/prueba")->assertRedirect('/login');
});

it('imprime automáticamente la prueba solo cuando se pide', function () {
    $this->actingAs($this->admin)->get("/cajas/{$this->caja->id}/prueba")->assertDontSee('setTimeout(() => window.print()', false);
    $this->actingAs($this->admin)->get("/cajas/{$this->caja->id}/prueba?imprimir=1")->assertSee('setTimeout(() => window.print()', false);
});

it('la pantalla de etiquetas arma la lista con copias', function () {
    $a = Articulo::factory()->create(['clave' => '7501055300075', 'descripcion' => 'COLA 600', 'precio_1' => '18']);
    $b = Articulo::factory()->create(['clave' => 'XYZ', 'descripcion' => 'OTRO', 'precio_1' => '5.5']);

    Volt::actingAs($this->cajero)->test('pages.articulos.etiquetas')
        ->dispatch('articulo-elegido', id: $a->id)
        ->dispatch('articulo-elegido', id: $a->id)  // el segundo escaneo suma una copia
        ->dispatch('articulo-elegido', id: $b->id)
        ->assertSet('items.0.copias', 2)
        ->assertSet('items.1.precio', '5.50')
        ->assertSeeHtml('<strong>3</strong> etiquetas')
        ->set('items.1.copias', 500)
        ->assertSet('items.1.copias', 200)
        ->set('items.1.copias', 0)
        ->assertSet('items.1.copias', 1);
});

it('las etiquetas se limitan a 500 y el formato es válido', function () {
    $a = Articulo::factory()->create();

    $c = Volt::actingAs($this->cajero)->test('pages.articulos.etiquetas')
        ->dispatch('articulo-elegido', id: $a->id)
        ->set('items.0.copias', 200);

    expect($c->instance()->etiquetas())->toHaveCount(200);

    $c->set('formato', 'raro')->assertSet('formato', 'rollo');
});

it('quita artículos de la lista de etiquetas', function () {
    $a = Articulo::factory()->create();

    Volt::actingAs($this->cajero)->test('pages.articulos.etiquetas')
        ->dispatch('articulo-elegido', id: $a->id)
        ->call('quitar', 0)
        ->assertSet('items', [])
        ->assertSee('Todavía no hay artículos');
});

it('el código de barras de la etiqueta usa la clave del artículo', function () {
    $a = Articulo::factory()->create(['clave' => '7501055300075', 'descripcion' => 'COLA 600', 'precio_1' => '18']);

    Volt::actingAs($this->cajero)->test('pages.articulos.etiquetas')
        ->dispatch('articulo-elegido', id: $a->id)
        ->assertSeeHtml('x-data="etiqueta(')->assertSeeHtml('<div class="clave">7501055300075</div>')
        ->assertSee('COLA 600');
});

it('los visitantes no entran a etiquetas', function () {
    $this->get('/articulos/etiquetas')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/articulos/etiquetas')->assertForbidden();
});
