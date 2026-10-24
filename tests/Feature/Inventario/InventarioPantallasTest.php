<?php

use App\Actions\Compras\RegistrarCompra;
use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\MetodoCosteo;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Configuracion;
use App\Models\Conteo;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->articulo = Articulo::factory()->create(['clave' => '7501', 'descripcion' => 'LECHE ENTERA']);
});

// ─── Permisos de rutas ───────────────────────────────────────────────────────

it('el cajero no entra a inventario, compras ni proveedores', function (string $ruta) {
    $this->actingAs($this->cajero)->get($ruta)->assertForbidden();
})->with(['/inventario', '/inventario/ajustes', '/inventario/conteos', '/compras', '/compras/crear', '/proveedores']);

it('los visitantes son enviados al acceso', function (string $ruta) {
    $this->get($ruta)->assertRedirect('/login');
})->with(['/inventario', '/compras', '/proveedores']);

it('el administrador ve las pantallas principales', function (string $ruta) {
    $this->actingAs($this->admin)->get($ruta)->assertOk();
})->with(['/inventario', '/inventario/ajustes', '/inventario/conteos', '/compras', '/compras/crear', '/proveedores', '/guia']);

// ─── Selector ────────────────────────────────────────────────────────────────

it('el selector elige por clave exacta con Enter', function () {
    Volt::actingAs($this->admin)->test('articulos.selector')
        ->set('texto', '7501')
        ->call('entrar')
        ->assertDispatched('articulo-elegido', id: $this->articulo->id)
        ->assertSet('texto', '');
});

it('el selector busca por nombre y elige el resultado único con Enter', function () {
    Volt::actingAs($this->admin)->test('articulos.selector')
        ->set('texto', 'leche')
        ->assertSee('LECHE ENTERA')
        ->call('entrar')
        ->assertDispatched('articulo-elegido', id: $this->articulo->id);
});

it('el selector avisa cuando no encuentra nada y no elige artículos inactivos', function () {
    Volt::actingAs($this->admin)->test('articulos.selector')
        ->set('texto', 'inexistente')
        ->call('entrar')
        ->assertSee('No hay ningún artículo')
        ->assertNotDispatched('articulo-elegido');

    $this->articulo->update(['activo' => false]);

    Volt::actingAs($this->admin)->test('articulos.selector')
        ->call('elegir', $this->articulo->id)
        ->assertNotDispatched('articulo-elegido');
});

it('el selector solo de inventariables rechaza servicios', function () {
    $servicio = Articulo::factory()->create(['inventariable' => false]);

    Volt::actingAs($this->admin)->test('articulos.selector', ['soloInventariables' => true])
        ->call('elegir', $servicio->id)
        ->assertNotDispatched('articulo-elegido')
        ->assertSee('no lleva control de inventario');
});

// ─── Inventario y kardex ─────────────────────────────────────────────────────

it('muestra existencias, alertas y filtros en el inventario', function () {
    $bajo = Articulo::factory()->create(['descripcion' => 'ARROZ BAJO', 'existencia_minima' => '5']);
    $ok = Articulo::factory()->create(['descripcion' => 'FRIJOL SUFICIENTE', 'existencia_minima' => '5']);
    app(RegistrarMovimientoInventario::class)($bajo, TipoMovimiento::Ajuste, '2');
    app(RegistrarMovimientoInventario::class)($ok, TipoMovimiento::Ajuste, '20');

    Volt::actingAs($this->admin)->test('pages.inventario.index')
        ->assertSee('ARROZ BAJO')->assertSee('FRIJOL SUFICIENTE')
        ->set('filtro', 'bajo')->assertSee('ARROZ BAJO')->assertDontSee('FRIJOL SUFICIENTE')
        ->set('filtro', 'agotados')->assertSee('LECHE ENTERA')->assertDontSee('FRIJOL SUFICIENTE');
});

it('el kardex lista los movimientos y permite filtrar', function () {
    $mov = app(RegistrarMovimientoInventario::class);
    $mov($this->articulo, TipoMovimiento::Ajuste, '10', motivo: 'correccion', nota: 'Inventario inicial');
    $mov($this->articulo, TipoMovimiento::Venta, '-2');

    Volt::actingAs($this->admin)->test('pages.inventario.kardex', ['articulo' => $this->articulo])
        ->assertSee('Inventario inicial')->assertSee('Venta')
        ->set('tipo', 'venta')->assertSee('Venta')->assertDontSee('Inventario inicial');

    $this->actingAs($this->admin)->get("/inventario/kardex/{$this->articulo->id}")->assertOk()->assertSee('LECHE ENTERA');
});

// ─── Ajustes ─────────────────────────────────────────────────────────────────

it('guarda un ajuste desde la pantalla', function () {
    app(RegistrarMovimientoInventario::class)($this->articulo, TipoMovimiento::Ajuste, '10');

    Volt::actingAs($this->admin)->test('pages.inventario.ajustes')
        ->dispatch('articulo-elegido', id: $this->articulo->id)
        ->assertSee('LECHE ENTERA')
        ->set('motivo', 'caducidad')
        ->set('cantidad', '4')
        ->set('nota', 'Se venció el lote')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSee('Ajuste guardado');

    expect($this->articulo->refresh()->existencia)->toBe('6.000');
});

it('la pantalla de ajustes muestra los errores de validación', function () {
    Volt::actingAs($this->admin)->test('pages.inventario.ajustes')
        ->dispatch('articulo-elegido', id: $this->articulo->id)
        ->set('cantidad', '0')
        ->call('guardar')
        ->assertHasErrors('cantidad');

    expect(MovimientoInventario::count())->toBe(0);
});

it('forzar la salida cuando el motivo es merma', function () {
    Volt::actingAs($this->admin)->test('pages.inventario.ajustes')
        ->set('direccion', 'entrada')
        ->set('motivo', 'merma')
        ->assertSet('direccion', 'salida');
});

// ─── Conteo ──────────────────────────────────────────────────────────────────

it('flujo completo de conteo físico', function () {
    app(RegistrarMovimientoInventario::class)($this->articulo, TipoMovimiento::Ajuste, '10');

    Volt::actingAs($this->admin)->test('pages.inventario.conteos')
        ->set('nombre', 'Conteo de prueba')
        ->call('crear')
        ->assertRedirect();

    $conteo = Conteo::first();

    Volt::actingAs($this->admin)->test('pages.inventario.conteo', ['conteo' => $conteo])
        ->dispatch('articulo-elegido', id: $this->articulo->id)
        ->set('cantidad', '7')
        ->call('guardarCantidad')
        ->assertHasNoErrors()
        ->assertSee('-3')
        ->call('aplicar')
        ->assertSee('Conteo aplicado');

    expect($this->articulo->refresh()->existencia)->toBe('7.000')->and($conteo->refresh()->estado)->toBe('aplicado');
});

it('el conteo rechaza cantidades inválidas y se puede cancelar', function () {
    $conteo = Conteo::create(['nombre' => 'X', 'user_id' => $this->admin->id]);

    Volt::actingAs($this->admin)->test('pages.inventario.conteo', ['conteo' => $conteo])
        ->dispatch('articulo-elegido', id: $this->articulo->id)
        ->set('cantidad', 'abc')
        ->call('guardarCantidad')
        ->assertHasErrors('cantidad')
        ->call('cancelar');

    expect($conteo->refresh()->estado)->toBe('cancelado');
});

// ─── Proveedores ─────────────────────────────────────────────────────────────

it('administra proveedores', function () {
    Volt::actingAs($this->admin)->test('pages.proveedores')
        ->call('nuevo')
        ->set('nombre', 'Abarrotes del Centro')->set('rfc', 'aaa010101aaa')->set('correo', 'ventas@centro.test')
        ->call('guardar')->assertHasNoErrors();

    $p = Proveedor::first();
    expect($p->nombre)->toBe('Abarrotes del Centro')->and($p->rfc)->toBe('AAA010101AAA');

    Volt::actingAs($this->admin)->test('pages.proveedores')
        ->call('nuevo')->set('nombre', 'Abarrotes del Centro')->call('guardar')->assertHasErrors('nombre')
        ->call('nuevo')->set('nombre', 'Otro')->set('correo', 'no-es-correo')->call('guardar')->assertHasErrors('correo')
        ->call('alternar', $p->id);

    expect($p->refresh()->activo)->toBeFalse();
});

// ─── Compras ─────────────────────────────────────────────────────────────────

it('flujo completo de compra, detalle y cancelación', function () {
    $proveedor = Proveedor::factory()->create();

    $componente = Volt::actingAs($this->admin)->test('pages.compras.crear')
        ->dispatch('articulo-elegido', id: $this->articulo->id)
        ->dispatch('articulo-elegido', id: $this->articulo->id) // el segundo escaneo suma una pieza
        ->assertSet('lineas.0.cantidad', '2')
        ->set('proveedor_id', (string) $proveedor->id)
        ->set('lineas.0.costo_unitario', '12.50')
        ->assertSee('25.00')
        ->call('guardar')
        ->assertHasNoErrors();

    $compra = Compra::first();
    expect($compra->total)->toBe('25.00')
        ->and($this->articulo->refresh()->existencia)->toBe('2.000');

    $this->actingAs($this->admin)->get("/compras/{$compra->id}")->assertOk()->assertSee($compra->folio)->assertSee('LECHE ENTERA');

    Volt::actingAs($this->admin)->test('pages.compras.ver', ['compra' => $compra])
        ->set('motivo', '')->call('cancelar')->assertHasErrors('motivo')
        ->set('motivo', 'Error de captura')->call('cancelar')->assertHasNoErrors()->assertSee('Compra cancelada');

    expect($compra->refresh()->estado)->toBe('cancelada')->and($this->articulo->refresh()->existencia)->toBe('0.000');
});

it('la compra exige proveedor y al menos un artículo', function () {
    Volt::actingAs($this->admin)->test('pages.compras.crear')
        ->call('guardar')
        ->assertHasErrors(['proveedor_id', 'lineas']);

    expect(Compra::count())->toBe(0);
});

it('la lista de compras filtra por folio y proveedor', function () {
    $p1 = Proveedor::factory()->create(['nombre' => 'PROVEEDOR UNO']);
    $p2 = Proveedor::factory()->create(['nombre' => 'PROVEEDOR DOS']);
    $compra = fn ($p) => app(RegistrarCompra::class)(
        ['proveedor_id' => $p->id, 'fecha' => now()->toDateString()],
        [['articulo_id' => $this->articulo->id, 'cantidad' => '1', 'costo_unitario' => '5']],
        $this->admin,
    );
    $compra($p1);
    $compra($p2);

    Volt::actingAs($this->admin)->test('pages.compras.index')
        ->assertSee('C-000001')->assertSee('C-000002')
        ->set('proveedor', (string) $p1->id)->assertSee('C-000001')->assertDontSee('C-000002')
        ->set('proveedor', '')->set('buscar', 'C-000002')->assertSee('C-000002')->assertDontSee('C-000001');
});

it('solo quien tiene permiso cancela compras', function () {
    $compra = app(RegistrarCompra::class)(
        ['proveedor_id' => Proveedor::factory()->create()->id, 'fecha' => now()->toDateString()],
        [['articulo_id' => $this->articulo->id, 'cantidad' => '1', 'costo_unitario' => '5']],
        $this->admin,
    );
    $verSolo = User::factory()->create();
    $verSolo->givePermissionTo('compras.ver');

    Volt::actingAs($verSolo)->test('pages.compras.ver', ['compra' => $compra])
        ->set('motivo', 'x')->call('cancelar')->assertForbidden();
});

// ─── Configuración de costeo ─────────────────────────────────────────────────

it('guarda el método de costeo y la venta sin existencia', function () {
    Volt::actingAs($this->admin)->test('pages.configuracion')
        ->set('form.nombre_tienda', 'Mi tienda')
        ->set('form.metodo_costeo', 'ultimo')
        ->set('form.vender_sin_existencia', false)
        ->call('guardar')
        ->assertHasNoErrors();

    $c = Configuracion::actual();
    expect($c->metodo_costeo)->toBe(MetodoCosteo::Ultimo)->and($c->vender_sin_existencia)->toBeFalse();
});

it('rechaza un método de costeo inválido', function () {
    Volt::actingAs($this->admin)->test('pages.configuracion')
        ->set('form.metodo_costeo', 'fifo')
        ->call('guardar')
        ->assertHasErrors('form.metodo_costeo');
});
