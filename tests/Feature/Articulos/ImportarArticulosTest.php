<?php

use App\Actions\Articulos\ImportarArticulos;
use App\Enums\Rol;
use App\Exports\PlantillaArticulos;
use App\Imports\LectorHoja;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\HistorialPrecio;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->importar = app(ImportarArticulos::class);
});

function fila(array $extra = []): array
{
    return array_merge([
        'clave' => '7501', 'descripcion' => 'REFRESCO COLA', 'departamento' => 'Bebidas', 'unidad' => 'pza',
        'costo' => '10', 'precio_1' => '18', 'precio_2' => '', 'precio_3' => '', 'impuestos' => 'IVA 16%',
        'inventariable' => 'SI', 'granel' => 'NO', 'existencia_minima' => '', 'existencia_maxima' => '',
        'claves_alternas' => '', 'activo' => 'SI',
    ], $extra);
}

it('crea artículos nuevos y departamentos que no existían', function () {
    $r = ($this->importar)([fila(), fila(['clave' => '7502', 'descripcion' => 'AGUA', 'impuestos' => '', 'claves_alternas' => 'A1, A2'])], $this->admin, true);

    expect($r['errores'])->toBe([])->and($r['creados'])->toBe(2)->and($r['actualizados'])->toBe(0)
        ->and(Departamento::where('nombre', 'Bebidas')->count())->toBe(1)
        ->and(Articulo::where('clave', '7502')->first()->clavesAlternas->pluck('clave')->all())->toEqualCanonicalizing(['A1', 'A2'])
        ->and(Articulo::where('clave', '7501')->first()->impuestos->pluck('nombre')->all())->toBe(['IVA 16%']);
});

it('actualiza por clave y conserva lo que viene vacío', function () {
    ($this->importar)([fila(['precio_2' => '17'])], $this->admin, true);

    $r = ($this->importar)([['clave' => '7501', 'descripcion' => '', 'precio_1' => '20']], $this->admin, true);

    $a = Articulo::where('clave', '7501')->first();
    expect($r['actualizados'])->toBe(1)->and($r['creados'])->toBe(0)
        ->and($a->descripcion)->toBe('REFRESCO COLA')
        ->and($a->precio_1)->toBe('20.00')
        ->and($a->precio_2)->toBe('17.00')
        ->and($a->impuestos)->toHaveCount(1)
        ->and(HistorialPrecio::where('articulo_id', $a->id)->where('origen', 'importacion')->where('campo', 'precio_1')->count())->toBe(2);
});

it('es de todo o nada y reporta la fila exacta de cada error', function () {
    $r = ($this->importar)([
        fila(['clave' => 'OK1']),
        fila(['clave' => 'MAL1', 'unidad' => 'galones']),
        fila(['clave' => 'MAL2', 'precio_1' => 'barato']),
        fila(['clave' => 'MAL3', 'granel' => 'quizá']),
        fila(['clave' => 'MAL4', 'impuestos' => 'IVA 99%']),
        fila(['clave' => 'OK1']), // repetida dentro del mismo archivo
    ], $this->admin, true);

    expect($r['creados'])->toBe(0)->and(Articulo::count())->toBe(0)
        ->and(collect($r['errores'])->pluck('fila')->all())->toBe([3, 4, 5, 6, 7])
        ->and($r['errores'][0]['mensajes'][0])->toContain('galones')
        ->and($r['errores'][1]['mensajes'][0])->toContain('barato')
        ->and($r['errores'][4]['clave'])->toBe('OK1');

    // También se deshace el departamento que se había creado
    expect(Departamento::where('nombre', 'Bebidas')->count())->toBe(0);
});

it('exige departamento y unidad en artículos nuevos', function () {
    $r = ($this->importar)([fila(['departamento' => '']), fila(['clave' => 'X', 'unidad' => ''])], $this->admin, true);

    expect($r['errores'])->toHaveCount(2)
        ->and($r['errores'][0]['mensajes'][0])->toContain('departamento')
        ->and($r['errores'][1]['mensajes'][0])->toContain('unidad');
});

it('acepta precios con símbolo de moneda y separador de miles', function () {
    ($this->importar)([fila(['precio_1' => '$1,250.50', 'costo' => '1 000'])], $this->admin, true);

    expect(Articulo::first()->precio_1)->toBe('1250.50')->and(Articulo::first()->costo)->toBe('1000.0000');
});

it('ignora filas vacías', function () {
    $r = ($this->importar)([fila(), array_fill_keys(array_keys(fila()), ''), fila(['clave' => '7503'])], $this->admin, true);

    expect($r['creados'])->toBe(2);
});

it('sin permiso de precios no guarda costos ni precios y lo avisa', function () {
    $r = ($this->importar)([fila()], $this->admin, false);

    expect($r['sin_permiso_precios'])->toBeTrue()->and(Articulo::first()->precio_1)->toBe('0.00');
});

it('lee un archivo de Excel respetando los códigos de barras largos', function () {
    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet();
    $hoja->fromArray(['Clave', 'Descripción', 'Departamento', 'Unidad', 'Precio 1'], null, 'A1');
    $hoja->setCellValueExplicit('A2', 7501055300075, 'n');
    $hoja->fromArray(['LECHE', 'Lácteos', 'pza', 24.5], null, 'B2');
    $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($libro))->save($ruta);

    $filas = LectorHoja::leer($ruta);
    unlink($ruta);

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['clave'])->toBe('7501055300075')
        ->and($filas[0]['precio_1'])->toBe('24.5')
        ->and($filas[0]['descripcion'])->toBe('LECHE');
});

it('rechaza archivos sin los encabezados de la plantilla', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    file_put_contents($ruta, "foo,bar\n1,2\n");

    expect(fn () => LectorHoja::leer($ruta))->toThrow(InvalidArgumentException::class);
    unlink($ruta);
});

it('importa desde la pantalla con un CSV y muestra el resultado', function () {
    $csv = "clave,descripcion,departamento,unidad,costo,precio_1,impuestos\n7501,REFRESCO,Bebidas,pza,10,18,IVA 16%\n";

    Volt::actingAs($this->admin)->test('pages.articulos.importar')
        ->set('archivo', UploadedFile::fake()->createWithContent('articulos.csv', $csv))
        ->call('importar')
        ->assertSee('Importación terminada')
        ->assertSee('1 artículo nuevo');

    expect(Articulo::count())->toBe(1);
});

it('la pantalla muestra los errores por fila y no guarda nada', function () {
    $csv = "clave,descripcion,departamento,unidad,precio_1\nA,UNO,General,pza,5\nB,DOS,General,galon,5\n";

    Volt::actingAs($this->admin)->test('pages.articulos.importar')
        ->set('archivo', UploadedFile::fake()->createWithContent('articulos.csv', $csv))
        ->call('importar')
        ->assertSee('1 fila con error')
        ->assertSee('galon');

    expect(Articulo::count())->toBe(0);
});

it('rechaza archivos que no son de hoja de cálculo', function () {
    Volt::actingAs($this->admin)->test('pages.articulos.importar')
        ->set('archivo', UploadedFile::fake()->create('virus.exe', 10))
        ->assertHasErrors('archivo');
});

it('permite descargar la plantilla', function () {
    Volt::actingAs($this->admin)->test('pages.articulos.importar')
        ->call('plantilla')
        ->assertFileDownloaded('plantilla-articulos.xlsx');
});

it('solo quien tiene permiso puede importar', function () {
    $cajero = User::factory()->conRol(Rol::Cajero)->create();

    Volt::actingAs($cajero)->test('pages.articulos.importar')->assertForbidden();
});

// ─── Proveedor y existencia ──────────────────────────────────────────────────

it('asigna el proveedor principal, lo crea si no existe y lo conserva si la celda viene vacía', function () {
    $r = ($this->importar)([fila(['proveedor' => 'Distribuidora Norte']), fila(['clave' => '7502', 'descripcion' => 'AGUA', 'proveedor' => 'distribuidora   norte'])], $this->admin, true);

    expect($r['errores'])->toBe([]);
    $a = Articulo::where('clave', '7501')->first();
    expect(Proveedor::count())->toBe(1)->and($a->proveedor->nombre)->toBe('Distribuidora Norte')
        ->and(Articulo::where('clave', '7502')->first()->proveedor_id)->toBe($a->proveedor_id);

    ($this->importar)([['clave' => '7501', 'descripcion' => '', 'precio_1' => '25', 'proveedor' => '']], $this->admin, true);
    expect($a->refresh()->proveedor_id)->not->toBeNull();
});

it('una importación sin columna de proveedor no se lo quita a los artículos', function () {
    $proveedor = Proveedor::factory()->create();
    ($this->importar)([fila()], $this->admin, true);
    Articulo::where('clave', '7501')->update(['proveedor_id' => $proveedor->id]);

    ($this->importar)([fila(['precio_1' => '30'])], $this->admin, true);

    expect(Articulo::where('clave', '7501')->first()->proveedor_id)->toBe($proveedor->id);
});

it('la columna existencia da de alta con existencia inicial, ignora negativos y no toca artículos que ya existían', function () {
    $r = ($this->importar)([
        fila(['existencia' => '12.5', 'unidad' => 'kg']),
        fila(['clave' => '7502', 'descripcion' => 'NEGATIVO', 'existencia' => '-2']),
        fila(['clave' => '7503', 'descripcion' => 'SIN DATO', 'existencia' => '']),
    ], $this->admin, true);

    expect($r['errores'])->toBe([])->and($r['avisos'])->toHaveCount(1)->and($r['avisos'][0])->toContain('existencia negativa');
    expect(Articulo::where('clave', '7501')->first()->existencia)->toBe('12.500')
        ->and(Articulo::where('clave', '7502')->first()->existencia)->toBe('0.000')
        ->and(Articulo::where('clave', '7501')->first()->movimientos()->first()->tipo->value)->toBe('inicial');

    $segunda = ($this->importar)([['clave' => '7501', 'existencia' => '99']], $this->admin, true);
    expect(Articulo::where('clave', '7501')->first()->existencia)->toBe('12.500')->and($segunda['avisos'][0])->toContain('no se cargó');
});

it('sin permiso de ajustar inventario la existencia se ignora en lugar de romper la importación', function () {
    $sinAjuste = User::factory()->create();
    $sinAjuste->givePermissionTo(['articulos.importar', 'articulos.crear', 'articulos.editar']);

    $r = ($this->importar)([fila(['existencia' => '5'])], $sinAjuste, true);

    expect($r['errores'])->toBe([])->and($r['creados'])->toBe(1)->and(Articulo::where('clave', '7501')->first()->existencia)->toBe('0.000')
        ->and($r['avisos'][0])->toContain('no se cargó');
});

it('lee los encabezados de otros sistemas: clave1…clave6, precio1, inv_min, (s/n) y PROVEEDOR', function () {
    $hoja = new Spreadsheet;
    $s = $hoja->getActiveSheet();
    $s->fromArray([
        ['clave1 *', 'clave2', 'clave3', 'descripción *', 'departamento', 'unidad', 'costo', 'precio1', 'precio2', '(s/n) granel', '(s/n) imp IVA 16% (16%)', '(s/n) imp IEPS (8%)', 'existencia', 'inv_min', 'inv_max', 'PROVEEDOR'],
        ['7501011154902', '7500478044405', 'ALT-3', 'PAKETAXO MEZCLADITO', 'BOTANAS', 'PIEZA', 47.195, 61, '', 'n', 's', 's', 4, 2, 8, 'Sabritas SA'],
    ]);
    $ruta = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
    (new Xlsx($hoja))->save($ruta);

    $filas = LectorHoja::leer($ruta);
    unlink($ruta);

    expect($filas[0])->toMatchArray([
        'clave' => '7501011154902', 'descripcion' => 'PAKETAXO MEZCLADITO', 'precio_1' => '61', 'granel' => 'n',
        'existencia_minima' => '2', 'existencia_maxima' => '8', 'proveedor' => 'Sabritas SA', 'claves_alternas' => '7500478044405,ALT-3',
        'impuestos' => 'IVA 16%,IEPS 8%',
    ]);

    $r = ($this->importar)($filas, $this->admin, true);
    $a = Articulo::where('clave', '7501011154902')->first();

    expect($r['errores'])->toBe([])->and($a->existencia)->toBe('4.000')->and($a->existencia_minima)->toBe('2.000')
        ->and($a->clavesAlternas->pluck('clave')->all())->toEqualCanonicalizing(['7500478044405', 'ALT-3'])
        ->and($a->impuestos->pluck('nombre')->all())->toEqualCanonicalizing(['IVA 16%', 'IEPS 8%'])
        ->and($a->proveedor->nombre)->toBe('Sabritas SA')->and($a->unidad->abreviatura)->toBe('pza');
});

it('la plantilla trae las columnas existencia y proveedor', function () {
    expect(PlantillaArticulos::ENCABEZADOS)->toContain('existencia', 'proveedor', 'claves_alternas')
        ->and(count(PlantillaArticulos::ENCABEZADOS))->toBe(17);
});
