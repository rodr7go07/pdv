<?php

use App\Actions\Proveedores\GuardarProveedor;
use App\Enums\Rol;
use App\Models\Proveedor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->guardar = app(GuardarProveedor::class);
});

it('crea y edita un proveedor limpiando espacios y el RFC', function () {
    $p = ($this->guardar)(null, ['nombre' => ' Abarrotes SA ', 'contacto' => ' ', 'rfc' => 'xaxx010101000', 'activo' => true], $this->admin);

    expect($p->fresh())->nombre->toBe('Abarrotes SA')->contacto->toBeNull()->rfc->toBe('XAXX010101000');

    ($this->guardar)($p, ['nombre' => 'Abarrotes SA', 'telefono' => '5551234567', 'activo' => false], $this->admin);

    expect($p->fresh())->telefono->toBe('5551234567')->activo->toBeFalse()->and(Proveedor::count())->toBe(1);
});

it('no permite dos proveedores con el mismo nombre, pero sí editar el propio', function () {
    $p = Proveedor::factory()->create(['nombre' => 'Lácteos']);
    Proveedor::factory()->create(['nombre' => 'Bebidas']);

    ($this->guardar)($p, ['nombre' => 'Lácteos', 'activo' => true], $this->admin);

    try {
        ($this->guardar)($p, ['nombre' => 'Bebidas', 'activo' => true], $this->admin);
        $this->fail('Debió fallar la validación.');
    } catch (ValidationException $e) {
        expect($e->errors()['nombre'][0])->toBe('Ya existe un proveedor con ese nombre.');
    }
});

it('exige el permiso de administrar proveedores', function () {
    ($this->guardar)(null, ['nombre' => 'X'], $this->cajero);
})->throws(HttpException::class);

it('guarda los días de visita ordenados y sin repetir, y los puede quitar', function () {
    $p = ($this->guardar)(null, ['nombre' => 'Sabritas', 'dias_visita' => ['4', '1', '4'], 'activo' => true], $this->admin);

    expect($p->fresh()->dias_visita)->toBe([1, 4])->and($p->fresh()->visitaTexto())->toBe('Lun, Jue');

    ($this->guardar)($p, ['nombre' => 'Sabritas', 'dias_visita' => [], 'activo' => true], $this->admin);
    expect($p->fresh()->dias_visita)->toBeNull()->and($p->fresh()->visitaTexto())->toBeNull();
});

it('rechaza días de visita fuera de la semana', function () {
    foreach ([['0'], ['8'], ['lunes']] as $dias) {
        try {
            ($this->guardar)(null, ['nombre' => 'X'.implode('', $dias), 'dias_visita' => $dias], $this->admin);
            $this->fail('Debió fallar: '.implode(',', $dias));
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('dias_visita.0');
        }
    }
});

it('calcula los días que hay que cubrir hasta la siguiente visita', function () {
    $lunesYJueves = new Proveedor(['dias_visita' => [1, 4]]);

    expect($lunesYJueves->diasHastaSiguienteVisita(CarbonImmutable::parse('2026-11-02')))->toBe(3)  // lunes: pasa hoy, cubre hasta el jueves
        ->and($lunesYJueves->diasHastaSiguienteVisita(CarbonImmutable::parse('2026-11-03')))->toBe(6) // martes: jueves y luego lunes
        ->and($lunesYJueves->diasHastaSiguienteVisita(CarbonImmutable::parse('2026-11-05')))->toBe(4) // jueves: hasta el lunes
        ->and((new Proveedor(['dias_visita' => [3]]))->diasHastaSiguienteVisita(CarbonImmutable::parse('2026-11-04')))->toBe(7)
        ->and((new Proveedor(['dias_visita' => null]))->diasHastaSiguienteVisita())->toBeNull();
});
