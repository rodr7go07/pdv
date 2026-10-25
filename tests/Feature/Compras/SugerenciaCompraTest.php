<?php

use App\Actions\Compras\RegistrarCompra;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Departamento;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\SugerenciaCompra;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A']);
    abrirTurno($this->caja, $this->encargado);

    $this->vender = fn (Articulo $a, string $cantidad) => app(RegistrarVenta::class)(
        Turno::abiertoDe($this->encargado)->caja, $this->encargado,
        [['articulo_id' => $a->id, 'cantidad' => $cantidad]],
        [['forma' => 'efectivo', 'monto' => '100000']],
    );

    // La existencia solo se mueve con movimientos; aquí se fija directo para armar el escenario.
    $this->existencia = fn (Articulo $a, string $cantidad) => Articulo::query()->whereKey($a->id)->update(['existencia' => $cantidad]);
    $this->fila = fn (Articulo $a, ?SugerenciaCompra $s = null) => ($s ?? new SugerenciaCompra)->filas()->firstWhere('id', $a->id);
});

it('sugiere lo que falta para cubrir los días de cobertura al ritmo de venta', function () {
    $a = Articulo::factory()->create(['precio_1' => '20', 'costo' => '8']);
    ($this->vender)($a, '30');                  // 30 en 30 días = 1 por día
    ($this->existencia)($a, '5');

    $f = ($this->fila)($a);                     // 15 días de cobertura → objetivo 15

    expect($f['sugerido'])->toBe('10')
        ->and($f['promedio'])->toBe('1.00')
        ->and($f['dias_restantes'])->toBe('5.0')
        ->and($f['estado'])->toBe('cobertura')
        ->and($f['importe'])->toBe('80.00');
});

it('no sugiere artículos que ya cubren los días de cobertura', function () {
    $a = Articulo::factory()->create();
    ($this->vender)($a, '3');
    ($this->existencia)($a, '100');

    expect(($this->fila)($a))->toBeNull();
});

it('un artículo bajo el mínimo se repone hasta el mínimo aunque no se haya vendido', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '5']);
    ($this->existencia)($a, '2');

    $f = ($this->fila)($a);

    expect($f['sugerido'])->toBe('3')->and($f['estado'])->toBe('bajo_minimo')->and($f['dias_restantes'])->toBeNull();
});

it('el mínimo manda cuando la venta pide menos que él', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '20']);
    ($this->vender)($a, '3');                   // cobertura pide 1.5, el mínimo pide 20
    ($this->existencia)($a, '8');

    expect(($this->fila)($a)['sugerido'])->toBe('12');
});

it('el máximo limita la compra pero nunca por debajo del mínimo', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '5', 'existencia_maxima' => '12']);
    ($this->vender)($a, '90');                  // 3 por día → 45 en 15 días, pero el máximo es 12
    ($this->existencia)($a, '4');

    expect(($this->fila)($a)['sugerido'])->toBe('8');
});

it('redondea hacia arriba, y a granel conserva tres decimales', function () {
    $pieza = Articulo::factory()->create();
    ($this->vender)($pieza, '10');              // 10/30 por día × 15 = 5; existencia 2.5 → faltan 2.5 → 3
    ($this->existencia)($pieza, '2.5');

    $kilos = Articulo::factory()->create(['granel' => true]);
    ($this->vender)($kilos, '10');
    ($this->existencia)($kilos, '2.5');

    expect(($this->fila)($pieza)['sugerido'])->toBe('3')
        ->and(($this->fila)($kilos)['sugerido'])->toBe('2.500');
});

it('las devoluciones restan y las ventas canceladas no cuentan', function () {
    $a = Articulo::factory()->create();
    $v1 = ($this->vender)($a, '30');
    app(RegistrarDevolucion::class)($v1, [['partida_id' => $v1->partidas->first()->id, 'cantidad' => '10']], 'Dañado', $this->encargado);
    $v2 = ($this->vender)($a, '60');
    app(CancelarVenta::class)($v2, 'Error', $this->encargado);
    ($this->existencia)($a, '0');

    // Neto vendido = 20 → 20/30 por día × 15 = 10
    expect(($this->fila)($a)['sugerido'])->toBe('10')->and(($this->fila)($a)['estado'])->toBe('agotado');
});

it('solo cuenta las ventas del periodo elegido', function () {
    $a = Articulo::factory()->create();
    $this->travelTo(now()->subDays(20));
    ($this->vender)($a, '30');
    $this->travelBack();
    ($this->existencia)($a, '0');

    expect(($this->fila)($a, new SugerenciaCompra(7)))->toBeNull()
        ->and(($this->fila)($a, new SugerenciaCompra(30))['sugerido'])->toBe('15');
});

it('filtra por departamento e ignora artículos inactivos o sin inventario', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '5']);
    $inactivo = Articulo::factory()->create(['existencia_minima' => '5', 'activo' => false]);
    $servicio = Articulo::factory()->create(['existencia_minima' => '5', 'inventariable' => false]);
    foreach ([$a, $inactivo, $servicio] as $x) {
        ($this->existencia)($x, '0');
    }

    $ids = (new SugerenciaCompra)->filas()->pluck('id');
    expect($ids->all())->toBe([$a->id])
        ->and((new SugerenciaCompra(30, 15, $a->departamento_id + 999))->filas())->toBeEmpty();
});

it('ordena primero lo agotado, luego lo bajo el mínimo y luego lo de cobertura baja', function () {
    $cobertura = Articulo::factory()->create();
    ($this->vender)($cobertura, '30');
    ($this->existencia)($cobertura, '5');
    $bajo = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($bajo, '4');
    $agotado = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($agotado, '0');

    expect((new SugerenciaCompra)->filas()->pluck('id')->all())->toBe([$agotado->id, $bajo->id, $cobertura->id]);
});

it('la pantalla exige permiso de compras y muestra la sugerencia', function () {
    $this->withoutVite();
    $a = Articulo::factory()->create(['descripcion' => 'LECHE ENTERA 1 L', 'existencia_minima' => '5']);
    ($this->existencia)($a, '1');

    $this->actingAs($this->cajero)->get('/compras/sugerencia')->assertForbidden();
    $this->actingAs($this->encargado)->get('/compras/sugerencia')->assertOk();

    Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')
        ->assertSee('LECHE ENTERA 1 L')->assertSee('Bajo el mínimo')
        ->set('cobertura', '30')->assertSee('LECHE ENTERA 1 L');
});

it('crear compra abre la compra con las partidas y cantidades sugeridas', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($a, '4');

    Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')
        ->call('crearCompra')->assertRedirect(route('compras.crear'));

    expect(session('compra_sugerida'))->toMatchArray(['partidas' => [$a->id => '6']]);

    Volt::actingAs($this->encargado)->test('pages.compras.crear')
        ->assertSet('lineas.0.articulo_id', $a->id)->assertSet('lineas.0.cantidad', '6');
});

it('la compra precargada caduca a los 10 minutos y se carga una sola vez', function () {
    $a = Articulo::factory()->create();

    session()->put('compra_sugerida', ['en' => now()->subMinutes(11)->timestamp, 'partidas' => [$a->id => '3']]);
    Volt::actingAs($this->encargado)->test('pages.compras.crear')->assertSet('lineas', []);

    session()->put('compra_sugerida', ['en' => now()->timestamp, 'partidas' => [$a->id => '3']]);
    Volt::actingAs($this->encargado)->test('pages.compras.crear')->assertSet('lineas.0.articulo_id', $a->id);

    expect(session('compra_sugerida'))->toBeNull();   // se consume: recargar la página no la repite
});

it('la compra precargada trae como máximo 500 partidas y avisa cuando hay más', function () {
    // Una sola unidad y un solo departamento: la fábrica crearía uno nuevo por artículo.
    $comunes = ['unidad_medida_id' => UnidadMedida::query()->value('id'), 'departamento_id' => Departamento::factory()->create()->id];
    $ids = Articulo::factory()->count(RegistrarCompra::MAXIMO_PARTIDAS + 2)->create($comunes)->pluck('id');
    session()->put('compra_sugerida', ['en' => now()->timestamp, 'partidas' => $ids->mapWithKeys(fn ($id) => [$id => '1'])->all()]);

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.crear');

    expect($pantalla->get('lineas'))->toHaveCount(RegistrarCompra::MAXIMO_PARTIDAS);
    $pantalla->assertSee('Se cargaron los primeros 500 de 502');
});

it('exporta la sugerencia aunque el enlace traiga valores que no son números', function () {
    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?dias=abc&cobertura=xyz&departamento=%27')->assertOk()->assertDownload();
    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?cobertura[]=1')->assertOk();
});

it('exporta la sugerencia a Excel con los mismos filtros', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '5']);
    ($this->existencia)($a, '0');

    $this->actingAs($this->cajero)->get('/compras/sugerencia/exportar')->assertForbidden();

    $r = $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?dias=30&cobertura=15');
    $r->assertOk()->assertDownload();
    expect($r->headers->get('content-disposition'))->toContain('sugerencia-de-compra-')->toContain('.xlsx');
});
