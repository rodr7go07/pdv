<?php

use App\Actions\Escaneos\RegistrarEscaneo;
use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Escaneo;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create(['name' => 'Ana Admin']);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto Cajero']);
    $this->leche = Articulo::factory()->create(['clave' => '7501055363057', 'descripcion' => 'LECHE ENTERA 1 L']);
    $this->registrar = fn (string $codigo, OrigenEscaneo $origen = OrigenEscaneo::Caja, MedioEscaneo $medio = MedioEscaneo::Lector, ?User $u = null) => app(RegistrarEscaneo::class)($codigo, $origen, $medio, $u ?? $this->cajero);
});

// ─── Acción ──────────────────────────────────────────────────────────────────

it('registra el código con su pantalla, medio, persona y el artículo que encontró', function () {
    $e = ($this->registrar)('7501055363057', OrigenEscaneo::Compras, MedioEscaneo::Camara);

    expect($e->codigo)->toBe('7501055363057')
        ->and($e->origen)->toBe(OrigenEscaneo::Compras)
        ->and($e->medio)->toBe(MedioEscaneo::Camara)
        ->and($e->user_id)->toBe($this->cajero->id)
        ->and($e->articulo_id)->toBe($this->leche->id)
        ->and($e->encontrado)->toBeTrue();
});

it('también encuentra el artículo por una clave alterna y anota los códigos sin artículo', function () {
    $this->leche->clavesAlternas()->create(['clave' => 'ALT-1']);

    expect(($this->registrar)('ALT-1')->articulo_id)->toBe($this->leche->id);

    $sin = ($this->registrar)('0000000000000');
    expect($sin->encontrado)->toBeFalse()->and($sin->articulo_id)->toBeNull();
});

it('guarda la caja del turno abierto de quien escanea', function () {
    $caja = Caja::factory()->create(['serie' => 'A']);
    abrirTurno($caja, $this->cajero);

    expect(($this->registrar)('7501055363057')->caja_id)->toBe($caja->id)
        ->and(($this->registrar)('7501055363057', u: $this->admin)->caja_id)->toBeNull();
});

it('no registra nombres escritos, vacíos ni textos demasiado largos', function () {
    expect(($this->registrar)('leche entera'))->toBeNull()
        ->and(($this->registrar)('   '))->toBeNull()
        ->and(($this->registrar)(str_repeat('1', 81)))->toBeNull()
        ->and(($this->registrar)("1234\xB1\x31"))->toBeNull()   // UTF-8 inválido
        ->and(Escaneo::count())->toBe(0);
});

it('un nombre de una sola palabra no cuenta como código sin artículo, pero una clave con dígitos sí', function () {
    expect(($this->registrar)('leche'))->toBeNull()
        ->and(($this->registrar)('Café'))->toBeNull()
        ->and(($this->registrar)('ALT-1'))->not->toBeNull();

    // Si coincide con un artículo se anota aunque no tenga dígitos (una clave como «PAN»).
    Articulo::factory()->create(['clave' => 'PAN']);
    expect(($this->registrar)('PAN')->encontrado)->toBeTrue()
        ->and(Escaneo::count())->toBe(2);
});

it('un artículo borrado de verdad después de escanearlo no cuenta como código pendiente', function () {
    ($this->registrar)('7501055363057');
    $this->leche->forceDelete();

    expect(Escaneo::first())->encontrado->toBeTrue()->articulo_id->toBeNull()
        ->and(Escaneo::sinArticulo()->count())->toBe(0);

    Volt::actingAs($this->admin)->test('pages.escaneos')->assertSee('Artículo eliminado')->assertDontSee('Dar de alta');
});

// ─── Puntos de captura ───────────────────────────────────────────────────────

it('la caja registra lo que llega por el campo de captura pero no la búsqueda por nombre', function () {
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501055363057&escaneo=1&medio=camara', $ajax)->assertOk();
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501055363057&escaneo=1', $ajax)->assertOk();
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501055363057', $ajax)->assertOk();             // búsqueda: no cuenta
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=leche%20entera&escaneo=1', $ajax)->assertOk();  // nombre: no cuenta
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501055363057&escaneo=1')->assertOk();           // sin X-Requested-With: no cuenta

    expect(Escaneo::count())->toBe(2)
        ->and(Escaneo::where('medio', 'camara')->count())->toBe(1)
        ->and(Escaneo::where('medio', 'lector')->count())->toBe(1)
        ->and(Escaneo::first()->origen)->toBe(OrigenEscaneo::Caja);
});

it('la búsqueda de la caja no se rompe con parámetros en forma de arreglo', function () {
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q[]=1&escaneo=1&medio[]=x', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    $this->actingAs($this->cajero)->getJson('/venta/buscar?q=7501055363057&escaneo=1&medio[]=x', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

    expect(Escaneo::count())->toBe(1)->and(Escaneo::first()->medio)->toBe(MedioEscaneo::Lector);
});

it('el selector de artículos registra con la pantalla que lo usa', function () {
    Volt::actingAs($this->admin)->test('articulos.selector', ['origen' => 'ajustes'])
        ->set('texto', '7501055363057')->call('entrar')
        ->set('texto', '0000000000000')->call('entrar', 'camara')
        ->set('texto', '7501055363057')->call('entrar', 'inventado');

    $e = Escaneo::orderBy('id')->get();
    expect($e)->toHaveCount(3)
        ->and($e->pluck('origen')->map->value->unique()->all())->toBe(['ajustes'])
        ->and($e->pluck('medio')->map->value->all())->toBe(['lector', 'camara', 'lector']);
});

it('un origen inválido del selector se queda en compras', function () {
    Volt::actingAs($this->admin)->test('articulos.selector', ['origen' => 'cualquier-cosa'])
        ->set('texto', '7501055363057')->call('entrar');

    expect(Escaneo::first()->origen)->toBe(OrigenEscaneo::Compras);
});

it('la lista de artículos registra el código buscado con Enter', function () {
    Volt::actingAs($this->admin)->test('pages.articulos.index')
        ->set('buscar', '7501055363057')->call('buscarClave', 'camara');

    expect(Escaneo::first())->origen->toBe(OrigenEscaneo::Articulos)->medio->toBe(MedioEscaneo::Camara);
});

it('la cámara avisa por /escaneos en las pantallas que no consultan al servidor', function () {
    $this->postJson('/escaneos', ['codigo' => '7501055363057', 'origen' => 'inventario'])->assertUnauthorized();

    $this->actingAs($this->admin)->postJson('/escaneos', ['codigo' => '7501055363057', 'origen' => 'articulo_clave'])->assertNoContent();
    $this->actingAs($this->admin)->postJson('/escaneos', ['codigo' => '7501055363057', 'origen' => 'no-existe'])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson('/escaneos', ['codigo' => '7501055363057', 'origen' => 'articulo_clave', 'medio' => 'inventado'])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson('/escaneos', ['codigo' => str_repeat('9', 81), 'origen' => 'inventario'])->assertUnprocessable();

    expect(Escaneo::count())->toBe(1)
        ->and(Escaneo::first())->medio->toBe(MedioEscaneo::Camara)->origen->toBe(OrigenEscaneo::ArticuloClave);
});

it('el formulario de artículo anota lo que llega con Enter (lector USB) como lector', function () {
    $this->actingAs($this->admin)->postJson('/escaneos', ['codigo' => '7501055363057', 'origen' => 'articulo_clave', 'medio' => 'lector'])->assertNoContent();

    expect(Escaneo::first())->medio->toBe(MedioEscaneo::Lector)->origen->toBe(OrigenEscaneo::ArticuloClave);

    $this->withoutVite();
    $this->actingAs($this->admin)->get('/articulos/crear')->assertOk()
        ->assertSee("anotarEscaneo('articulo_clave', \$el.value)", false)
        ->assertSee("anotarEscaneo('articulo_clave', nueva)", false);
});

// ─── Vista ───────────────────────────────────────────────────────────────────

it('la vista de escaneos exige permiso de reportes', function () {
    $this->actingAs($this->cajero)->get('/escaneos')->assertForbidden();

    $this->withoutVite();
    $this->actingAs($this->admin)->get('/escaneos')->assertOk()->assertSee('Códigos escaneados');
});

it('lista los escaneos con su artículo y marca los que no tienen artículo', function () {
    ($this->registrar)('7501055363057', OrigenEscaneo::Caja, MedioEscaneo::Camara);
    ($this->registrar)('0000000000000', OrigenEscaneo::Compras);

    Volt::actingAs($this->admin)->test('pages.escaneos')
        ->assertSee('7501055363057')->assertSee('LECHE ENTERA 1 L')->assertSee('Venta')->assertSee('Cámara')
        ->assertSee('0000000000000')->assertSee('Sin artículo')->assertSee('Dar de alta')->assertSee('Beto Cajero');
});

it('filtra por código, pantalla, medio, resultado, persona y fechas', function () {
    ($this->registrar)('7501055363057', OrigenEscaneo::Caja, MedioEscaneo::Camara);
    ($this->registrar)('1111111111111', OrigenEscaneo::Compras, MedioEscaneo::Lector, $this->admin);
    $viejo = ($this->registrar)('2222222222222', OrigenEscaneo::Inventario);
    $viejo->forceFill(['created_at' => now()->subDays(10)])->save();

    $pantalla = Volt::actingAs($this->admin)->test('pages.escaneos');

    $pantalla->set('buscar', '1111')->assertSee('1111111111111')->assertDontSee('7501055363057')
        ->set('buscar', '')->set('origen', 'caja')->assertSee('7501055363057')->assertDontSee('1111111111111')
        ->set('origen', '')->set('medio', 'camara')->assertSee('7501055363057')->assertDontSee('2222222222222')
        ->set('medio', '')->set('resultado', 'encontrados')->assertSee('7501055363057')->assertDontSee('1111111111111')
        ->set('resultado', 'sin_articulo')->assertDontSee('7501055363057')->assertSee('1111111111111')
        ->set('resultado', '')->set('usuario', (string) $this->admin->id)->assertSee('1111111111111')->assertDontSee('7501055363057')
        ->set('usuario', '')->set('desde', now()->subDays(3)->toDateString())->assertDontSee('2222222222222')->assertSee('7501055363057')
        ->call('limpiar')->assertSee('2222222222222');
});

it('un código escaneado antes de dar de alta el artículo muestra el artículo que ya existe', function () {
    ($this->registrar)('7506656903748', OrigenEscaneo::ArticuloClave, MedioEscaneo::Camara);   // aún no existe
    ($this->registrar)('ALT-9', OrigenEscaneo::Caja);                                           // tampoco
    ($this->registrar)('0000000000000');                                                         // sigue sin existir

    expect(Escaneo::where('encontrado', false)->count())->toBe(3);

    Articulo::factory()->create(['clave' => '7506656903748', 'descripcion' => 'BOLÍGRAFO VERDE']);
    $this->leche->clavesAlternas()->create(['clave' => 'ALT-9']);

    $pantalla = Volt::actingAs($this->admin)->test('pages.escaneos');
    $pantalla->assertSee('BOLÍGRAFO VERDE')->assertSee('LECHE ENTERA 1 L')->assertSee('Ya dado de alta');
    expect($pantalla->instance()->resumen)->toBe(['total' => 3, 'sin_articulo' => 1, 'camara' => 1]);

    $pantalla->set('resultado', 'sin_articulo')->assertSee('0000000000000')->assertDontSee('7506656903748')->assertDontSee('ALT-9')
        ->set('resultado', 'encontrados')->assertSee('7506656903748')->assertSee('ALT-9')->assertDontSee('0000000000000');
});

it('un artículo eliminado ya no cuenta como dado de alta', function () {
    ($this->registrar)('7506656903748');
    $nuevo = Articulo::factory()->create(['clave' => '7506656903748']);
    $nuevo->delete();

    expect(Volt::actingAs($this->admin)->test('pages.escaneos')->instance()->resumen['sin_articulo'])->toBe(1);
});

it('el resumen cuenta escaneos, los que no tienen artículo y los de cámara', function () {
    ($this->registrar)('7501055363057', OrigenEscaneo::Caja, MedioEscaneo::Camara);
    ($this->registrar)('0000000000000', OrigenEscaneo::Caja, MedioEscaneo::Camara);
    ($this->registrar)('7501055363057');

    expect(Volt::actingAs($this->admin)->test('pages.escaneos')->instance()->resumen)
        ->toBe(['total' => 3, 'sin_articulo' => 1, 'camara' => 2]);
});

it('dar de alta un código sin artículo abre el formulario con la clave ya escrita', function () {
    $this->withoutVite();

    // La clave viaja en la dirección; queda en el estado del componente (aparece en su «snapshot»).
    $this->actingAs($this->admin)->get('/articulos/crear?clave=0000000000000')->assertOk()
        ->assertSee('&quot;clave&quot;:&quot;0000000000000&quot;', false);

    $this->actingAs($this->admin)->get('/articulos/crear')->assertOk()
        ->assertDontSee('&quot;clave&quot;:&quot;0000000000000&quot;', false);
});

// ─── Depuración ──────────────────────────────────────────────────────────────

it('la depuración rechaza un plazo que no sea un número entero y no borra nada', function () {
    $viejo = ($this->registrar)('2222222222222');
    $viejo->forceFill(['created_at' => now()->subDays(5)])->save();

    $this->artisan('escaneos:depurar', ['--dias' => 'abc'])->assertFailed();
    $this->artisan('escaneos:depurar', ['--dias' => '0'])->assertFailed();
    $this->artisan('escaneos:depurar', ['--dias' => '-3'])->assertFailed();

    expect(Escaneo::count())->toBe(1);
});

it('la depuración borra solo los escaneos más viejos que el plazo', function () {
    $viejo = ($this->registrar)('2222222222222');
    $viejo->forceFill(['created_at' => now()->subDays(200)])->save();
    ($this->registrar)('7501055363057');

    $this->artisan('escaneos:depurar')->assertSuccessful();

    expect(Escaneo::count())->toBe(1)->and(Escaneo::first()->codigo)->toBe('7501055363057');

    $this->artisan('escaneos:depurar', ['--dias' => 1])->assertSuccessful();
    expect(Escaneo::count())->toBe(1);
});
