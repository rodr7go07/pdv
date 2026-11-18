<?php

use App\Actions\Articulos\ImportarArticulos;
use App\Enums\Rol;
use App\Exports\CatalogoArticulos;
use App\Imports\LectorHoja;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Maatwebsite\Excel\Excel as Formato;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $bebidas = Departamento::factory()->create(['nombre' => 'Bebidas']);
    $proveedor = Proveedor::factory()->create(['nombre' => 'Distribuidora Norte']);
    $kg = UnidadMedida::where('abreviatura', 'kg')->first();
    $pza = UnidadMedida::where('abreviatura', 'pza')->first();

    $this->coca = Articulo::factory()->create([
        'clave' => '0750105530007', 'descripcion' => 'COCA COLA 600 ML', 'departamento_id' => $bebidas->id, 'unidad_medida_id' => $pza->id,
        'costo' => '10.5', 'precio_1' => '18', 'precio_2' => '17', 'precio_3' => null, 'existencia' => '24',
        'existencia_minima' => '6', 'existencia_maxima' => '48', 'proveedor_id' => $proveedor->id, 'inventariable' => true, 'granel' => false,
    ]);
    $this->coca->impuestos()->sync([Impuesto::where('nombre', 'IVA 16%')->value('id')]);
    $this->coca->clavesAlternas()->create(['clave' => '75010553']);

    $this->manzana = Articulo::factory()->create([
        'clave' => 'FRUTA-001', 'descripcion' => 'MANZANA ROJA', 'departamento_id' => $bebidas->id, 'unidad_medida_id' => $kg->id,
        'costo' => '22', 'precio_1' => '35', 'existencia' => '12.5', 'granel' => true, 'activo' => false,
    ]);
});

function exportarCatalogoA(bool $conExistencias = true): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
    file_put_contents($ruta, Excel::raw(new CatalogoArticulos($conExistencias), Formato::XLSX));

    return $ruta;
}

it('lo exportado se vuelve a importar igual en un sistema vacío', function () {
    $ruta = exportarCatalogoA();
    $antes = Articulo::query()->with(['impuestos', 'clavesAlternas', 'proveedor', 'departamento', 'unidad'])->orderBy('clave')->get();

    // Sistema vacío: como producción el primer día (los catálogos base sí existen).
    DB::table('articulo_claves_alternas')->delete();
    DB::table('articulo_impuesto')->delete();
    Articulo::query()->forceDelete();

    $r = app(ImportarArticulos::class)(LectorHoja::leer($ruta), $this->admin, true);
    $despues = Articulo::query()->with(['impuestos', 'clavesAlternas', 'proveedor', 'departamento', 'unidad'])->orderBy('clave')->get();

    expect($r['creados'] ?? $despues->count())->toBe(2)->and($despues)->toHaveCount(2);
    foreach ($antes as $i => $a) {
        $b = $despues[$i];
        expect($b->clave)->toBe($a->clave) // el 0 del principio no se pierde
            ->and($b->descripcion)->toBe($a->descripcion)
            ->and($b->departamento->nombre)->toBe($a->departamento->nombre)
            ->and($b->unidad->abreviatura)->toBe($a->unidad->abreviatura)
            ->and((float) $b->costo)->toBe((float) $a->costo)
            ->and((float) $b->precio_1)->toBe((float) $a->precio_1)
            ->and($b->precio_2 === null ? null : (float) $b->precio_2)->toBe($a->precio_2 === null ? null : (float) $a->precio_2)
            ->and((float) $b->existencia)->toBe((float) $a->existencia)
            ->and($b->granel)->toBe($a->granel)
            ->and($b->activo)->toBe($a->activo)
            ->and($b->proveedor?->nombre)->toBe($a->proveedor?->nombre)
            ->and($b->impuestos->pluck('nombre')->all())->toBe($a->impuestos->pluck('nombre')->all())
            ->and($b->clavesAlternas->pluck('clave')->all())->toBe($a->clavesAlternas->pluck('clave')->all());
    }
});

it('sin existencias deja la columna vacía', function () {
    $filas = LectorHoja::leer(exportarCatalogoA(false));

    expect(collect($filas)->pluck('existencia')->filter(fn ($v) => $v !== null && $v !== '')->all())->toBe([]);
});

it('el comando guarda el archivo y la pantalla lo pide con permiso de costos', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'cmd').'.xlsx';
    $this->artisan('articulos:exportar', ['archivo' => $ruta])->assertSuccessful();
    expect(filesize($ruta))->toBeGreaterThan(1000);

    Volt::actingAs($this->admin)->test('pages.articulos.importar')->call('exportarCatalogo', true)->assertFileDownloaded();

    $sinCostos = User::factory()->create();
    $sinCostos->givePermissionTo('articulos.importar');
    Volt::actingAs($sinCostos)->test('pages.articulos.importar')->call('exportarCatalogo', true)->assertForbidden();
});
