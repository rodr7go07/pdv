<?php

use App\Actions\Articulos\GuardarArticulo;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\HistorialPrecio;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->depto = Departamento::factory()->create();
    $this->pieza = UnidadMedida::factory()->create();
    $this->kilo = UnidadMedida::factory()->decimal()->create();
    $this->iva = Impuesto::factory()->iva('16')->create();
    $this->guardar = app(GuardarArticulo::class);
});

function datosArticulo(array $extra = []): array
{
    return array_merge([
        'clave' => '7501',
        'descripcion' => 'REFRESCO COLA 600 ML',
        'departamento_id' => test()->depto->id,
        'unidad_medida_id' => test()->pieza->id,
        'costo' => '10',
        'precio_1' => '15',
        'inventariable' => true,
        'granel' => false,
        'activo' => true,
        'impuestos' => [],
        'claves_alternas' => [],
    ], $extra);
}

it('crea un artículo con impuestos y claves alternas', function () {
    $articulo = ($this->guardar)(null, datosArticulo([
        'impuestos' => [$this->iva->id],
        'claves_alternas' => ['ALT-1', 'ALT-2'],
        'precio_2' => '14', 'precio_3' => '',
    ]));

    expect($articulo->clave)->toBe('7501')
        ->and($articulo->impuestos)->toHaveCount(1)
        ->and($articulo->clavesAlternas->pluck('clave')->all())->toEqualCanonicalizing(['ALT-1', 'ALT-2'])
        ->and($articulo->precio_2)->toBe('14.00')
        ->and($articulo->precio_3)->toBeNull();
});

it('rechaza una clave repetida de otro artículo', function () {
    Articulo::factory()->create(['clave' => '7501']);

    expect(fn () => ($this->guardar)(null, datosArticulo()))->toThrow(ValidationException::class);
});

it('rechaza una clave principal que ya es clave alterna de otro artículo', function () {
    ($this->guardar)(null, datosArticulo(['clave' => 'A', 'claves_alternas' => ['COMUN']]));

    try {
        ($this->guardar)(null, datosArticulo(['clave' => 'COMUN']));
        $this->fail('Debió fallar');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('clave');
    }
});

it('rechaza una clave alterna que ya es clave principal o alterna de otro artículo', function () {
    Articulo::factory()->create(['clave' => 'EXISTE']);
    ($this->guardar)(null, datosArticulo(['clave' => 'B', 'claves_alternas' => ['OCUPADA']]));

    foreach (['EXISTE', 'OCUPADA'] as $clave) {
        expect(fn () => ($this->guardar)(null, datosArticulo(['clave' => 'C'.$clave, 'claves_alternas' => [$clave]])))
            ->toThrow(ValidationException::class);
    }
});

it('rechaza claves alternas repetidas o iguales a la principal', function () {
    expect(fn () => ($this->guardar)(null, datosArticulo(['claves_alternas' => ['X', 'x']])))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosArticulo(['claves_alternas' => ['7501']])))->toThrow(ValidationException::class);
});

it('no considera la clave alterna propia como duplicada al editar', function () {
    $articulo = ($this->guardar)(null, datosArticulo(['claves_alternas' => ['MIA']]));

    $editado = ($this->guardar)($articulo, datosArticulo(['descripcion' => 'OTRO NOMBRE', 'claves_alternas' => ['MIA', 'NUEVA']]));

    expect($editado->descripcion)->toBe('OTRO NOMBRE')
        ->and($editado->clavesAlternas->pluck('clave')->all())->toEqualCanonicalizing(['MIA', 'NUEVA']);
});

it('quita las claves alternas que ya no vienen', function () {
    $articulo = ($this->guardar)(null, datosArticulo(['claves_alternas' => ['A1', 'A2']]));

    $editado = ($this->guardar)($articulo, datosArticulo(['claves_alternas' => ['A2']]));

    expect($editado->clavesAlternas->pluck('clave')->all())->toBe(['A2']);
});

it('reserva la clave de un artículo eliminado', function () {
    $articulo = ($this->guardar)(null, datosArticulo());
    $articulo->delete();

    expect(fn () => ($this->guardar)(null, datosArticulo()))->toThrow(ValidationException::class);
});

it('exige unidad con decimales para artículos a granel', function () {
    expect(fn () => ($this->guardar)(null, datosArticulo(['granel' => true])))->toThrow(ValidationException::class);

    $ok = ($this->guardar)(null, datosArticulo(['granel' => true, 'unidad_medida_id' => $this->kilo->id]));
    expect($ok->granel)->toBeTrue();
});

it('valida que la existencia máxima no sea menor que la mínima', function () {
    expect(fn () => ($this->guardar)(null, datosArticulo(['existencia_minima' => '10', 'existencia_maxima' => '5'])))
        ->toThrow(ValidationException::class);
});

it('rechaza costos o precios negativos y departamentos inexistentes', function () {
    expect(fn () => ($this->guardar)(null, datosArticulo(['precio_1' => '-1'])))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosArticulo(['costo' => '-5'])))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosArticulo(['departamento_id' => 9999])))->toThrow(ValidationException::class);
});

it('registra el historial solo de lo que cambia', function () {
    $usuario = User::factory()->create();
    $articulo = ($this->guardar)(null, datosArticulo(), $usuario);

    // Alta: costo y precio 1 distintos de cero
    expect(HistorialPrecio::where('articulo_id', $articulo->id)->pluck('campo')->all())
        ->toEqualCanonicalizing(['costo', 'precio_1']);

    ($this->guardar)($articulo, datosArticulo(['precio_1' => '18', 'precio_2' => '17']), $usuario);

    $cambios = HistorialPrecio::where('articulo_id', $articulo->id)->latest('id')->take(2)->get()->keyBy('campo');

    expect($cambios)->toHaveKeys(['precio_1', 'precio_2'])
        ->and($cambios['precio_1']->valor_anterior)->toBe('15.0000')
        ->and($cambios['precio_1']->valor_nuevo)->toBe('18.0000')
        ->and($cambios['precio_2']->valor_anterior)->toBeNull()
        ->and($cambios['precio_1']->user_id)->toBe($usuario->id)
        ->and(HistorialPrecio::where('articulo_id', $articulo->id)->count())->toBe(4);
});

it('no genera historial si los precios no cambian', function () {
    $articulo = ($this->guardar)(null, datosArticulo());
    $antes = HistorialPrecio::count();

    ($this->guardar)($articulo, datosArticulo(['descripcion' => 'SOLO NOMBRE', 'costo' => '10.0000', 'precio_1' => '15.00']));

    expect(HistorialPrecio::count())->toBe($antes);
});

it('ignora costo y precios cuando el usuario no tiene permiso', function () {
    $articulo = ($this->guardar)(null, datosArticulo());

    $editado = ($this->guardar)($articulo, datosArticulo(['precio_1' => '999', 'costo' => '500', 'descripcion' => 'NUEVO']), puedePrecios: false);

    expect($editado->descripcion)->toBe('NUEVO')
        ->and($editado->precio_1)->toBe('15.00')
        ->and($editado->costo)->toBe('10.0000')
        ->and(HistorialPrecio::count())->toBe(2);
});

it('guarda la imagen y borra la anterior al reemplazarla', function () {
    $articulo = ($this->guardar)(null, datosArticulo(), imagen: UploadedFile::fake()->image('a.png'));
    $primera = $articulo->imagen_ruta;
    Storage::disk('public')->assertExists($primera);

    $articulo = ($this->guardar)($articulo, datosArticulo(), imagen: UploadedFile::fake()->image('b.png'));

    Storage::disk('public')->assertMissing($primera);
    Storage::disk('public')->assertExists($articulo->imagen_ruta);

    $articulo = ($this->guardar)($articulo, datosArticulo(), quitarImagen: true);
    expect($articulo->imagen_ruta)->toBeNull();
});

it('busca por clave, clave alterna o descripción', function () {
    $a = ($this->guardar)(null, datosArticulo(['clave' => 'AAA111', 'descripcion' => 'LECHE ENTERA', 'claves_alternas' => ['LECHE-ALT']]));
    ($this->guardar)(null, datosArticulo(['clave' => 'BBB222', 'descripcion' => 'PAN BLANCO']));

    expect(Articulo::buscar('aaa')->pluck('id')->all())->toBe([$a->id])
        ->and(Articulo::buscar('leche-alt')->pluck('id')->all())->toBe([$a->id])
        ->and(Articulo::buscar('blanco')->count())->toBe(1)
        ->and(Articulo::buscar('')->count())->toBe(2)
        ->and(Articulo::buscar('100%')->count())->toBe(0);
});

it('encuentra por clave exacta, principal o alterna', function () {
    $a = ($this->guardar)(null, datosArticulo(['clave' => 'AAA111', 'claves_alternas' => ['ZZZ9']]));

    expect(Articulo::porClave('AAA111')?->id)->toBe($a->id)
        ->and(Articulo::porClave('ZZZ9')?->id)->toBe($a->id)
        ->and(Articulo::porClave('AAA'))->toBeNull()
        ->and(Articulo::porClave(''))->toBeNull();
});

it('calcula margen y precio sin impuestos del artículo', function () {
    $a = ($this->guardar)(null, datosArticulo(['costo' => '100', 'precio_1' => '150.80', 'impuestos' => [$this->iva->id]]));

    expect($a->tasaImpuestos())->toBe('16.0000')
        ->and($a->margen())->toBe('30.00')
        ->and($a->precioSinImpuestos())->toBe('130.0000');
});
