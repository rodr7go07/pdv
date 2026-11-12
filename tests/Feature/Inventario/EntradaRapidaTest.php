<?php

use App\Actions\Compras\RegistrarCompra;
use App\Actions\Inventario\RegistrarEntradaRapida;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->bolillo = Articulo::factory()->create(['descripcion' => 'BOLILLO', 'clave' => 'BOL', 'existencia' => '10', 'costo' => '1.50', 'costo_promedio' => '1.50', 'ultimo_costo' => '1.50']);
    $this->leche = Articulo::factory()->create(['descripcion' => 'LECHE', 'clave' => 'LEC', 'existencia' => '4', 'costo' => '20', 'costo_promedio' => '20', 'ultimo_costo' => '20']);
});

it('sube la existencia sin proveedor y sin mover el costo, y queda como entrada en Compras', function () {
    $compra = app(RegistrarEntradaRapida::class)([
        ['articulo_id' => $this->bolillo->id, 'cantidad' => '30'],
        ['articulo_id' => $this->leche->id, 'cantidad' => '6'],
    ], $this->encargado);

    expect($compra->tipo)->toBe('entrada')->and($compra->proveedor_id)->toBeNull()
        ->and($compra->notas)->toBe('Entrada rápida')
        ->and((float) $this->bolillo->fresh()->existencia)->toBe(40.0)
        ->and((float) $this->leche->fresh()->existencia)->toBe(10.0)
        ->and((float) $this->bolillo->fresh()->costo)->toBe(1.5)
        ->and((float) $this->bolillo->fresh()->costo_promedio)->toBe(1.5)
        ->and(MovimientoInventario::query()->where('articulo_id', $this->bolillo->id)->value('tipo')->value)->toBe('compra');
});

it('solo quien puede cambiar costos usa un costo distinto', function () {
    app(RegistrarEntradaRapida::class)([['articulo_id' => $this->leche->id, 'cantidad' => '4', 'costo' => '30']], $this->encargado);
    expect((float) $this->leche->fresh()->ultimo_costo)->toBe(30.0);

    $sinCostos = User::factory()->create();
    $sinCostos->givePermissionTo('compras.registrar');
    app(RegistrarEntradaRapida::class)([['articulo_id' => $this->bolillo->id, 'cantidad' => '1', 'costo' => '99']], $sinCostos);
    expect((float) $this->bolillo->fresh()->ultimo_costo)->toBe(1.5);
});

it('exige permiso de registrar compras y valida la cantidad', function () {
    expect(fn () => app(RegistrarEntradaRapida::class)([['articulo_id' => $this->bolillo->id, 'cantidad' => '1']], $this->cajero))
        ->toThrow(HttpException::class);

    expect(fn () => app(RegistrarEntradaRapida::class)([['articulo_id' => $this->bolillo->id, 'cantidad' => '0']], $this->encargado))
        ->toThrow(ValidationException::class);
    expect((float) $this->bolillo->fresh()->existencia)->toBe(10.0);
});

it('una compra normal sigue exigiendo proveedor', function () {
    expect(fn () => app(RegistrarCompra::class)(
        ['proveedor_id' => null, 'fecha' => now()->toDateString()],
        [['articulo_id' => $this->bolillo->id, 'cantidad' => '1', 'costo_unitario' => '1']],
        $this->encargado,
    ))->toThrow(ValidationException::class);
});

it('la pantalla suma con cada lectura, deja cambiar la cantidad y guarda todo junto', function () {
    $proveedor = Proveedor::factory()->create();

    Volt::actingAs($this->encargado)->test('pages.inventario.entrada')
        ->call('agregar', $this->bolillo->id)
        ->call('agregar', $this->leche->id)
        ->call('agregar', $this->bolillo->id)
        ->assertSet('lineas.0.articulo_id', $this->bolillo->id)
        ->assertSet('lineas.0.cantidad', '2')
        ->set('lineas.1.cantidad', '12')
        ->call('sumar', 0, 1)
        ->assertSee('quedará')
        ->set('proveedor_id', (string) $proveedor->id)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('lineas', [])
        ->assertSee('Las existencias ya se actualizaron');

    expect((float) $this->bolillo->fresh()->existencia)->toBe(13.0)
        ->and((float) $this->leche->fresh()->existencia)->toBe(16.0)
        ->and(Compra::query()->sole()->proveedor_id)->toBe($proveedor->id);

    $this->actingAs($this->cajero)->get(route('inventario.entrada'))->assertForbidden();
});

it('el botón + de Inventario mete mercancía de un artículo', function () {
    $this->actingAs($this->encargado)->get(route('inventario.index'))->assertSee('Entrada de mercancía');

    Volt::actingAs($this->encargado)->test('pages.inventario.index')
        ->call('abrirEntrada', $this->bolillo->id)
        ->set('entradaCantidad', 'abc')
        ->call('guardarEntrada')
        ->assertHasErrors('entradaCantidad')
        ->set('entradaCantidad', '25')
        ->call('guardarEntrada')
        ->assertHasNoErrors()
        ->assertSee('Entraron 25');

    expect((float) $this->bolillo->fresh()->existencia)->toBe(35.0);

    Volt::actingAs($this->cajero)->test('pages.inventario.index')->assertDontSee('Meter mercancía de');
});

it('Compras muestra la entrada sin proveedor', function () {
    $compra = app(RegistrarEntradaRapida::class)([['articulo_id' => $this->bolillo->id, 'cantidad' => '2']], $this->encargado);

    $this->actingAs($this->encargado)->get(route('compras.index'))->assertOk()->assertSee('Entrada rápida');
    $this->actingAs($this->encargado)->get(route('compras.ver', $compra))->assertOk()->assertSee('Sin proveedor (entrada rápida)');
});
