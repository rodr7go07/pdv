<?php

use App\Actions\Compras\RegistrarCompra;
use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\SugerenciaCompra;
use Carbon\CarbonImmutable;
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

// ─── Edición de la sugerencia ────────────────────────────────────────────────

it('permite cambiar la cantidad de un renglón y la compra usa lo editado', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10', 'costo' => '5']);
    ($this->existencia)($a, '4');

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')
        ->call('fijarCantidad', $a->id, '9');

    $fila = $pantalla->get('filas')->firstWhere('id', $a->id);
    expect($fila['sugerido'])->toBe('9')->and($fila['editado'])->toBeTrue()->and($fila['importe'])->toBe('45.00')
        ->and($pantalla->get('total'))->toBe('45.00');

    $pantalla->call('crearCompra')->assertRedirect(route('compras.crear'));
    expect(session('compra_sugerida')['partidas'])->toBe([$a->id => '9']);
});

it('quita renglones, agrega artículos que no se sugirieron y vuelve a la sugerencia original', function () {
    $sugerido = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($sugerido, '4');
    $extra = Articulo::factory()->create(['existencia_minima' => '2', 'costo' => '3']);
    ($this->existencia)($extra, '50'); // arriba del mínimo: el cálculo no lo sugiere

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')
        ->call('quitar', $sugerido->id)
        ->call('agregar', $extra->id)
        ->call('fijarCantidad', $extra->id, '12');

    $filas = $pantalla->get('filas');
    expect($filas->pluck('id')->all())->toBe([$extra->id])
        ->and($filas->first()['manual'])->toBeTrue()->and($filas->first()['sugerido'])->toBe('12')->and($filas->first()['importe'])->toBe('36.00')
        ->and($pantalla->get('hayCambios'))->toBeTrue();

    $pantalla->call('restablecer');
    expect($pantalla->get('filas')->pluck('id')->all())->toBe([$sugerido->id])->and($pantalla->get('hayCambios'))->toBeFalse();
});

it('volver a agregar un artículo que se había quitado lo regresa con su cantidad sugerida', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($a, '4');

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')->call('quitar', $a->id);
    expect($pantalla->get('filas'))->toBeEmpty();

    $pantalla->call('agregar', $a->id);
    expect($pantalla->get('filas')->firstWhere('id', $a->id)['sugerido'])->toBe('6');
});

it('rechaza cantidades inválidas, decimales en piezas enteras y no deja crear la compra', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($a, '4');

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia');

    foreach (['abc', '', '0', '-3', '2.5', '1e3', '9999999999'] as $malo) {
        $pantalla->call('fijarCantidad', $a->id, $malo);
        expect($pantalla->get('filas')->firstWhere('id', $a->id)['error'])->not->toBeNull("«{$malo}» debió rechazarse");
    }

    $pantalla->call('crearCompra')->assertHasErrors('cantidades')->assertNoRedirect();

    $pantalla->call('fijarCantidad', $a->id, '7');
    expect($pantalla->get('filas')->firstWhere('id', $a->id)['error'])->toBeNull();
});

it('un artículo a granel acepta hasta tres decimales', function () {
    $kilo = Articulo::factory()->create(['granel' => true, 'existencia_minima' => '10', 'unidad_medida_id' => UnidadMedida::query()->where('abreviatura', 'kg')->value('id')]);
    ($this->existencia)($kilo, '1');

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')->call('fijarCantidad', $kilo->id, '2.5');
    expect($pantalla->get('filas')->firstWhere('id', $kilo->id))->toMatchArray(['sugerido' => '2.5', 'error' => null]);

    $pantalla->call('fijarCantidad', $kilo->id, '2.5555');
    expect($pantalla->get('filas')->firstWhere('id', $kilo->id)['error'])->not->toBeNull();
});

it('cambiar los filtros descarta lo editado a mano', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10']);
    ($this->existencia)($a, '4');

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')->call('fijarCantidad', $a->id, '99');
    expect($pantalla->get('hayCambios'))->toBeTrue();

    $pantalla->set('cobertura', '20');
    expect($pantalla->get('hayCambios'))->toBeFalse()->and($pantalla->get('cantidades'))->toBe([]);
});

it('el cajero no puede editar la sugerencia', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10']);
    Volt::actingAs($this->cajero)->test('pages.compras.sugerencia')->assertForbidden();

    // Quien solo puede consultar compras ve la sugerencia pero no la modifica.
    $consulta = User::factory()->create();
    $consulta->givePermissionTo('compras.ver');
    Volt::actingAs($consulta)->test('pages.compras.sugerencia')->call('quitar', $a->id)->assertForbidden();
});

it('exporta a Excel lo que se ve en pantalla, con los cambios', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '10', 'descripcion' => 'ARROZ EDITADO']);
    ($this->existencia)($a, '4');

    Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')
        ->call('fijarCantidad', $a->id, '25')->call('exportar')
        ->assertRedirect(route('compras.sugerencia.exportar', ['editada' => 1]));

    expect(session('sugerencia_editada')['filas'][0])->toMatchArray(['id' => $a->id, 'sugerido' => '25']);

    $this->actingAs($this->encargado)->withSession(['sugerencia_editada' => session('sugerencia_editada')])
        ->get('/compras/sugerencia/exportar?editada=1')->assertOk()->assertDownload();
});

it('una exportación editada caducada o ausente vuelve a calcular la sugerencia', function () {
    $a = Articulo::factory()->create(['existencia_minima' => '5']);
    ($this->existencia)($a, '0');

    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?editada=1')->assertOk()->assertDownload();
    $this->actingAs($this->encargado)->withSession(['sugerencia_editada' => ['en' => now()->subHour()->timestamp, 'dias' => 30, 'filas' => []]])
        ->get('/compras/sugerencia/exportar?editada=1')->assertOk()->assertDownload();
});

// ─── Por proveedor y por semana ──────────────────────────────────────────────

it('filtra la sugerencia por proveedor principal o por artículos sin proveedor', function () {
    $norte = Proveedor::factory()->create(['nombre' => 'Norte']);
    $delNorte = Articulo::factory()->create(['existencia_minima' => '10', 'proveedor_id' => $norte->id]);
    $sueltos = Articulo::factory()->create(['existencia_minima' => '10']);
    $otro = Articulo::factory()->create(['existencia_minima' => '10', 'proveedor_id' => Proveedor::factory()->create()->id]);
    foreach ([$delNorte, $sueltos, $otro] as $a) {
        ($this->existencia)($a, '1');
    }

    $ids = fn (?SugerenciaCompra $s) => $s->filas()->pluck('id')->all();

    expect($ids(new SugerenciaCompra))->toEqualCanonicalizing([$delNorte->id, $sueltos->id, $otro->id])
        ->and($ids(new SugerenciaCompra(30, 15, null, $norte->id)))->toBe([$delNorte->id])
        ->and($ids(new SugerenciaCompra(30, 15, null, SugerenciaCompra::SIN_PROVEEDOR)))->toBe([$sueltos->id])
        ->and((new SugerenciaCompra(30, 15, null, $norte->id))->filas()->first()['proveedor'])->toBe('Norte');
});

it('al elegir un proveedor con días de visita propone cubrir hasta su siguiente visita', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00')); // martes
    $p = Proveedor::factory()->create(['dias_visita' => [1, 4]]);
    Articulo::factory()->create(['proveedor_id' => $p->id]);
    $sinDias = Proveedor::factory()->create();
    Articulo::factory()->create(['proveedor_id' => $sinDias->id]);

    $pantalla = Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')->set('proveedor', (string) $p->id);
    expect($pantalla->get('cobertura'))->toBe('6')->and($pantalla->get('diasHastaVisita'))->toBe(6);
    $pantalla->assertSee('Hasta su siguiente visita (6 días)')->assertSee('Lun, Jue');

    $pantalla->call('usarCobertura', 7);
    expect($pantalla->get('cobertura'))->toBe('7');

    // Sin días de visita no cambia la cobertura y explica cómo ponerlos.
    $pantalla->set('proveedor', (string) $sinDias->id)->assertSee('no tiene días de visita');
    expect($pantalla->get('cobertura'))->toBe('7');
});

it('crear la compra desde la sugerencia de un proveedor trae el proveedor elegido', function () {
    $p = Proveedor::factory()->create();
    $a = Articulo::factory()->create(['existencia_minima' => '10', 'proveedor_id' => $p->id]);
    ($this->existencia)($a, '4');

    Volt::actingAs($this->encargado)->test('pages.compras.sugerencia')->set('proveedor', (string) $p->id)->call('crearCompra');

    Volt::actingAs($this->encargado)->test('pages.compras.crear')
        ->assertSet('proveedor_id', (string) $p->id)->assertSet('lineas.0.articulo_id', $a->id);
});

it('la exportación por proveedor trae solo sus artículos y la columna de proveedor', function () {
    $p = Proveedor::factory()->create(['nombre' => 'Norte']);
    $a = Articulo::factory()->create(['existencia_minima' => '5', 'proveedor_id' => $p->id]);
    ($this->existencia)($a, '0');

    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?proveedor='.$p->id)->assertOk()->assertDownload();
    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?proveedor=sin')->assertOk();
    $this->actingAs($this->encargado)->get('/compras/sugerencia/exportar?proveedor[]=1')->assertOk();
});
