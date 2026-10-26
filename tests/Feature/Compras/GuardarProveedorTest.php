<?php

use App\Actions\Proveedores\GuardarProveedor;
use App\Enums\Rol;
use App\Models\Proveedor;
use App\Models\User;
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
