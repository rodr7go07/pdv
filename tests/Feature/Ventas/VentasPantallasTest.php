<?php

use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaEnEspera;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja = Caja::factory()->create(['serie' => 'A', 'nombre' => 'Caja 1']);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Cajero Uno']);
    $this->otroCajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Cajero Dos']);
    $this->caja2 = Caja::factory()->create(['serie' => 'C', 'nombre' => 'Caja 2']);
    abrirTurno($this->caja, $this->cajero);
    abrirTurno($this->caja2, $this->otroCajero);
    $this->leche = Articulo::factory()->create([
        'clave' => '7501', 'descripcion' => 'LECHE ENTERA', 'precio_1' => '25', 'costo' => '15',
        'unidad_medida_id' => UnidadMedida::where('abreviatura', 'pza')->value('id'),
        'departamento_id' => Departamento::first()->id,
    ]);
    $this->leche->clavesAlternas()->create(['clave' => 'ALT-LECHE']);
});

function ventaDe(User $cajero, string $cantidad = '2'): Venta
{
    return app(RegistrarVenta::class)(
        Turno::abiertoDe($cajero)->caja, $cajero,
        [['articulo_id' => Articulo::first()->id, 'cantidad' => $cantidad]],
        [['forma' => 'efectivo', 'monto' => '500']],
    );
}

// ─── Permisos ────────────────────────────────────────────────────────────────

it('la caja exige permiso de cobrar', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)->get('/venta')->assertForbidden();
    $this->actingAs($sinPermiso)->getJson('/venta/buscar?q=7501')->assertForbidden();
    $this->actingAs($sinPermiso)->get('/ventas')->assertForbidden();
    $this->actingAs($this->cajero)->get('/venta')->assertOk();
});

it('los visitantes sin sesión no entran a la caja', function () {
    $this->get('/venta')->assertRedirect('/login');
    $this->getJson('/venta/buscar?q=1')->assertUnauthorized();
});

// ─── Búsqueda para el escáner ────────────────────────────────────────────────

it('el endpoint de búsqueda responde exacto por clave y por clave alterna', function () {
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501')
        ->assertOk()->assertJson(['exacto' => true])
        ->assertJsonPath('articulos.0.descripcion', 'LECHE ENTERA')
        ->assertJsonPath('articulos.0.precios.0', '25.00')
        ->assertJsonPath('articulos.0.decimales', false);

    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=ALT-LECHE')->assertJson(['exacto' => true])->assertJsonPath('articulos.0.id', $this->leche->id);
});

it('busca por descripción parcial y no devuelve inactivos', function () {
    Articulo::factory()->create(['descripcion' => 'LECHE DESCONTINUADA', 'activo' => false]);

    $r = $this->actingAs($this->cajero)->getJson('/venta/buscar?q=lech')->assertOk()->assertJson(['exacto' => false]);

    expect(collect($r->json('articulos'))->pluck('descripcion')->all())->toBe(['LECHE ENTERA']);
});

it('la búsqueda ignora textos vacíos o demasiado largos y no expone costos', function () {
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=')->assertOk()->assertJson(['articulos' => []]);
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q='.str_repeat('a', 200))->assertOk()->assertJson(['articulos' => []]);

    $r = $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501');
    expect(json_encode($r->json()))->not->toContain('costo');
});

it('devuelve los precios vigentes por id', function () {
    $this->actingAs($this->cajero)->getJson("/venta/por-ids?ids={$this->leche->id},9999")
        ->assertOk()->assertJsonCount(1, 'articulos');
});

// ─── Pantalla de caja ────────────────────────────────────────────────────────

it('la previsualización devuelve los totales exactos del servidor', function () {
    $iva = Impuesto::where('nombre', 'IVA 16%')->first();
    $this->leche->impuestos()->sync([$iva->id]);

    $r = Volt::actingAs($this->cajero)->test('pages.pos')
        ->call('previsualizar', [['articulo_id' => $this->leche->id, 'cantidad' => '2']], [], 1)
        ->assertSet('cajaId', $this->caja->id);

    expect(Volt::actingAs($this->cajero)->test('pages.pos')->instance()->previsualizar([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [], 1))
        ->toMatchArray(['ok' => true, 'total' => '50.00', 'subtotal' => '43.10', 'impuestos' => '6.90']);
});

it('la previsualización explica los errores sin lanzar excepciones', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    expect($c->previsualizar([], [], 1))->toMatchArray(['ok' => false])
        ->and($c->previsualizar([['articulo_id' => 99999, 'cantidad' => '1']], [], 1)['mensaje'])->toContain('disponible')
        ->and($c->previsualizar([['articulo_id' => $this->leche->id, 'cantidad' => '1', 'descuento_tipo' => 'pct', 'descuento_valor' => '10']], [], 1)['mensaje'])->toContain('permiso');
});

it('cobra desde la pantalla y devuelve folio y cambio', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    $r = $c->cobrar([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [['forma' => 'efectivo', 'monto' => '100']], [], 1);

    expect($r)->toMatchArray(['ok' => true, 'folio' => 'A-000001', 'total' => '50.00', 'cambio' => '50.00'])
        ->and(Venta::count())->toBe(1)
        ->and(Venta::first()->user_id)->toBe($this->cajero->id);
});

it('al cobrar con error no guarda nada y devuelve el mensaje', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    $r = $c->cobrar([['articulo_id' => $this->leche->id, 'cantidad' => '2']], [['forma' => 'efectivo', 'monto' => '10']], [], 1);

    expect($r['ok'])->toBeFalse()->and($r['mensaje'])->toContain('Falta dinero')->and(Venta::count())->toBe(0);
});

it('la caja sin turno abierto pide abrirlo', function () {
    $sinTurno = User::factory()->conRol(Rol::Cajero)->create();

    Volt::actingAs($sinTurno)->test('pages.pos')->assertSee('Abre tu turno')->assertSee('Abrir mi turno');
    $c = Volt::actingAs($sinTurno)->test('pages.pos')->instance();
    expect($c->cobrar([['articulo_id' => $this->leche->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '50']], [], 1)['ok'])->toBeFalse();
});

it('ventas en espera: guardar, listar, recuperar y descartar', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();
    $linea = ['id' => $this->leche->id, 'clave' => '7501', 'descripcion' => 'LECHE ENTERA', 'unidad' => 'pza', 'decimales' => false, 'granel' => false, 'inventariable' => true, 'existencia' => '0', 'precios' => ['25.00', null, null], 'cantidad' => '3', 'descTipo' => '', 'descValor' => ''];

    expect($c->guardarEspera([$linea], ['tipo' => '', 'valor' => ''], 'Doña Mary'))->toBe(['ok' => true])
        ->and($c->guardarEspera([], [], null)['ok'])->toBeFalse();

    $lista = $c->listarEsperas();
    expect($lista)->toHaveCount(1)->and($lista[0]['nombre'])->toBe('Doña Mary')->and($lista[0]['piezas'])->toBe(1);

    $r = $c->recuperarEspera($lista[0]['id']);
    expect($r['ok'])->toBeTrue()->and($r['lineas'][0]['cantidad'])->toBe('3')
        ->and(VentaEnEspera::count())->toBe(0)
        ->and($c->recuperarEspera($lista[0]['id'])['ok'])->toBeFalse();

    $c->guardarEspera([$linea], [], null);
    $c->descartarEspera(VentaEnEspera::first()->id);
    expect(VentaEnEspera::count())->toBe(0);
});

it('no se recupera una espera de otra caja', function () {
    $otra = Caja::factory()->create(['serie' => 'B']);
    $espera = VentaEnEspera::create(['caja_id' => $otra->id, 'user_id' => $this->admin->id, 'carrito' => ['lineas' => []], 'total' => 0]);

    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    expect($c->recuperarEspera($espera->id)['ok'])->toBeFalse();
});

it('sanitiza lo que se guarda en espera', function () {
    $c = Volt::actingAs($this->cajero)->test('pages.pos')->instance();

    $c->guardarEspera([['id' => $this->leche->id, 'cantidad' => '2', 'descTipo' => 'hack', 'descValor' => '<script>', 'descripcion' => 'X', 'precios' => ['1'], 'evil' => 'campo-extra']], ['tipo' => 'xx', 'valor' => 'abc'], str_repeat('N', 200));

    $e = VentaEnEspera::first();
    expect($e->carrito['lineas'][0])->not->toHaveKey('evil')
        ->and($e->carrito['lineas'][0]['descTipo'])->toBe('')
        ->and($e->carrito['lineas'][0]['descValor'])->toBe('')
        ->and($e->carrito['descGlobal'])->toBe(['tipo' => '', 'valor' => ''])
        ->and(mb_strlen($e->nombre))->toBe(60);
});

it('el alta rápida exige permisos y crea el artículo', function () {
    $datos = ['clave' => 'NUEVO1', 'descripcion' => 'PRODUCTO NUEVO', 'precio' => '12.50', 'departamento_id' => Departamento::first()->id, 'unidad_medida_id' => UnidadMedida::first()->id, 'iva_id' => ''];

    $cajero = Volt::actingAs($this->cajero)->test('pages.pos')->instance();
    expect($cajero->altaRapida($datos)['ok'])->toBeFalse()->and(Articulo::where('clave', 'NUEVO1')->exists())->toBeFalse();

    $admin = Volt::actingAs($this->admin)->test('pages.pos')->instance();
    $r = $admin->altaRapida($datos);
    expect($r['ok'])->toBeTrue()->and($r['articulo']['precios'][0])->toBe('12.50')->and(Articulo::where('clave', 'NUEVO1')->exists())->toBeTrue();

    expect($admin->altaRapida($datos)['ok'])->toBeFalse(); // clave repetida
});

// ─── Historial de ventas ─────────────────────────────────────────────────────

it('el cajero solo ve sus propias ventas y el administrador todas', function () {
    $mia = ventaDe($this->cajero);
    $ajena = ventaDe($this->otroCajero);

    Volt::actingAs($this->cajero)->test('pages.ventas.index')->assertSee($mia->folio)->assertDontSee($ajena->folio);
    Volt::actingAs($this->admin)->test('pages.ventas.index')->assertSee($mia->folio)->assertSee($ajena->folio);
    Volt::actingAs($this->admin)->test('pages.ventas.index')->set('cajero', (string) $this->otroCajero->id)->assertSee($ajena->folio)->assertDontSee($mia->folio);
});

it('un cajero no abre ni imprime ventas ajenas', function () {
    $ajena = ventaDe($this->otroCajero);

    $this->actingAs($this->cajero)->get("/ventas/{$ajena->id}")->assertForbidden();
    $this->actingAs($this->cajero)->get("/ventas/{$ajena->id}/ticket")->assertForbidden();
    $this->actingAs($this->admin)->get("/ventas/{$ajena->id}")->assertOk();
    $this->actingAs($this->admin)->get("/ventas/{$ajena->id}/ticket")->assertOk()->assertSee($ajena->folio);
});

it('el cajero reimprime su propio ticket', function () {
    $mia = ventaDe($this->cajero);

    $this->actingAs($this->cajero)->get("/ventas/{$mia->id}/ticket")->assertOk()->assertSee('LECHE ENTERA')->assertSee('TOTAL');
});

it('el ticket de una venta cancelada lo indica y usa el ancho de la caja', function () {
    $venta = ventaDe($this->cajero);
    $this->caja->update(['ancho_papel' => 58]);
    app(CancelarVenta::class)($venta, 'Error', $this->admin);

    $this->actingAs($this->admin)->get("/ventas/{$venta->id}/ticket")->assertSee('VENTA CANCELADA')->assertSee('58mm', false);
});

it('imprime automáticamente solo cuando se pide', function () {
    $venta = ventaDe($this->cajero);

    $this->actingAs($this->cajero)->get("/ventas/{$venta->id}/ticket")->assertDontSee('setTimeout(() => window.print()', false)->assertSee('onclick="window.print()"', false);
    $this->actingAs($this->cajero)->get("/ventas/{$venta->id}/ticket?imprimir=1")->assertSee('setTimeout(() => window.print()', false);
});

it('cancela una venta desde el detalle', function () {
    $venta = ventaDe($this->cajero);

    Volt::actingAs($this->admin)->test('pages.ventas.ver', ['venta' => $venta])
        ->set('motivo', '')->call('cancelar')->assertHasErrors('motivo')
        ->set('motivo', 'Error del cajero')->call('cancelar')->assertHasNoErrors()->assertSee('Venta cancelada');

    expect($venta->refresh()->estado)->toBe('cancelada');
});

it('registra una devolución desde el detalle', function () {
    $venta = ventaDe($this->cajero, '3');
    $partida = $venta->partidas->first();

    Volt::actingAs($this->admin)->test('pages.ventas.ver', ['venta' => $venta])
        ->set("devolver.{$partida->id}", '1')
        ->set('motivoDevolucion', 'Producto dañado')
        ->call('registrarDevolucion')
        ->assertHasNoErrors()
        ->assertSee('D-000001')
        ->assertSee('Entrega ese dinero');

    expect($partida->refresh()->cantidad_devuelta)->toBe('1.000');
});

it('la devolución con datos incorrectos muestra el error', function () {
    $venta = ventaDe($this->cajero, '1');

    Volt::actingAs($this->admin)->test('pages.ventas.ver', ['venta' => $venta])
        ->set('motivoDevolucion', 'x')
        ->call('registrarDevolucion')
        ->assertHasErrors('devolucion');
});

it('el cajero no cancela ni devuelve por su cuenta desde el detalle: necesita un supervisor', function () {
    $venta = ventaDe($this->cajero);

    Volt::actingAs($this->cajero)->test('pages.ventas.ver', ['venta' => $venta])
        ->assertDontSee('Devolución')
        ->assertSee('Pedir autorización para cancelar')
        ->set('motivo', 'x')->call('cancelar')->assertHasErrors('autorizacion')
        ->set('devolver', [$venta->partidas[0]->id => '1'])->set('motivoDevolucion', 'x')->call('registrarDevolucion')->assertForbidden();

    expect($venta->refresh()->estado)->toBe('completada');
});

it('la caja ofrece escanear con la cámara del teléfono', function () {
    Volt::actingAs($this->cajero)->test('pages.pos')
        ->assertSee('Escanear con la cámara')
        ->assertSee('abrirCamara()', false);
});

it('la ventana Buscar de la caja permite escanear con la cámara', function () {
    Volt::actingAs($this->cajero)->test('pages.pos')
        ->assertSee("destino: 'pos-buscar'", false)
        ->assertSee('x-data="escanerCamara"', false)
        ->assertSee('codigoDeBuscar', false)
        // Un código desconocido leído con la cámara no abre el alta solo: ofrece este botón.
        ->assertSee('abrirAlta(codigoSinAlta)', false);
});
