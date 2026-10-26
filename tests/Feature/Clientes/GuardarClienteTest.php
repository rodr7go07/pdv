<?php

use App\Actions\Clientes\GuardarCliente;
use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create();
    $this->guardar = app(GuardarCliente::class);
});

it('crea un cliente limpiando espacios, vacíos y el RFC en mayúsculas', function () {
    $c = ($this->guardar)(null, [
        'nombre' => '  Juan Pérez ', 'telefono' => ' ', 'correo' => '', 'rfc' => 'xaxx010101000', 'direccion' => null,
        'lista_precios' => '2', 'limite_credito' => '1500', 'notas' => '', 'activo' => true,
    ], $this->admin);

    expect($c->fresh())
        ->nombre->toBe('Juan Pérez')->telefono->toBeNull()->correo->toBeNull()->rfc->toBe('XAXX010101000')
        ->lista_precios->toBe(2)->limite_credito->toBe('1500.00')->activo->toBeTrue();
});

it('sin el permiso de crédito conserva la lista y el límite del cliente', function () {
    $encargado = User::factory()->create();
    $encargado->givePermissionTo('clientes.administrar');
    $c = Cliente::factory()->conCredito('500')->create(['lista_precios' => 3]);

    ($this->guardar)($c, ['nombre' => 'Nuevo nombre', 'lista_precios' => '1', 'limite_credito' => '9999', 'activo' => true], $encargado);

    expect($c->fresh())->nombre->toBe('Nuevo nombre')->lista_precios->toBe(3)->limite_credito->toBe('500.00');
});

it('rechaza datos inválidos con mensajes en español', function () {
    try {
        ($this->guardar)(null, ['nombre' => '', 'correo' => 'x', 'rfc' => 'xx', 'lista_precios' => '9', 'limite_credito' => '-5'], $this->admin);
        $this->fail('Debió fallar la validación.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqualCanonicalizing(['nombre', 'correo', 'rfc', 'lista_precios', 'limite_credito'])
            ->and($e->errors()['rfc'][0])->toBe('El RFC no tiene un formato válido.');
    }
});

it('exige el permiso de administrar clientes', function () {
    ($this->guardar)(null, ['nombre' => 'X', 'lista_precios' => '1', 'limite_credito' => '0'], $this->cajero);
})->throws(HttpException::class);
