<?php

use App\Actions\Usuarios\GuardarUsuario;
use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create(['name' => 'Dueña', 'usuario' => 'duena']);
    $this->guardar = app(GuardarUsuario::class);
});

function datosUsuario(array $extra = []): array
{
    return array_merge(['name' => 'Ana López', 'usuario' => 'ana', 'email' => '', 'password' => 'clave-segura-1', 'rol' => 'Cajero', 'activo' => true], $extra);
}

it('solo el administrador entra a usuarios', function () {
    $this->actingAs($this->admin)->get('/usuarios')->assertOk()->assertSee('Qué puede hacer cada rol');
    $this->actingAs(User::factory()->conRol(Rol::Encargado)->create())->get('/usuarios')->assertForbidden();
    $this->actingAs(User::factory()->conRol(Rol::Cajero)->create())->get('/usuarios')->assertForbidden();
});

it('los visitantes no ven usuarios', function () {
    $this->get('/usuarios')->assertRedirect('/login');
});

it('da de alta un usuario con rol y contraseña cifrada', function () {
    $u = ($this->guardar)(null, datosUsuario(), $this->admin);

    expect($u->usuario)->toBe('ana')->and($u->nombreRol())->toBe('Cajero')->and($u->activo)->toBeTrue()
        ->and($u->email)->toBeNull()
        ->and(Hash::check('clave-segura-1', $u->password))->toBeTrue()
        ->and($u->password)->not->toBe('clave-segura-1');
});

it('el usuario nuevo puede entrar con su usuario y contraseña', function () {
    ($this->guardar)(null, datosUsuario(), $this->admin);

    Volt::test('pages.auth.login')->set('form.login', 'ana')->set('form.password', 'clave-segura-1')->call('login')->assertHasNoErrors();
    $this->assertAuthenticated();
});

it('normaliza el usuario a minúsculas y valida el formato', function () {
    expect((($this->guardar)(null, datosUsuario(['usuario' => ' PEDRO.R ']), $this->admin))->usuario)->toBe('pedro.r');

    foreach (['con espacio', 'ñandú', 'a', 'con@arroba'] as $malo) {
        expect(fn () => ($this->guardar)(null, datosUsuario(['usuario' => $malo]), $this->admin))->toThrow(ValidationException::class);
    }
});

it('no permite usuarios ni correos repetidos', function () {
    ($this->guardar)(null, datosUsuario(['email' => 'ana@tienda.test']), $this->admin);

    expect(fn () => ($this->guardar)(null, datosUsuario(['name' => 'Otra']), $this->admin))->toThrow(ValidationException::class)                          // mismo usuario
        ->and(fn () => ($this->guardar)(null, datosUsuario(['usuario' => 'otra', 'email' => 'ANA@tienda.test']), $this->admin))->toThrow(ValidationException::class); // mismo correo
});

it('exige contraseña de 8 caracteres en altas y un rol válido', function () {
    expect(fn () => ($this->guardar)(null, datosUsuario(['password' => '']), $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosUsuario(['password' => 'corta']), $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosUsuario(['rol' => 'Dios']), $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => ($this->guardar)(null, datosUsuario(['name' => '']), $this->admin))->toThrow(ValidationException::class);
});

it('al editar la contraseña es opcional y se conserva si va vacía', function () {
    $u = ($this->guardar)(null, datosUsuario(), $this->admin);

    $editado = ($this->guardar)($u, datosUsuario(['name' => 'Ana María', 'password' => '', 'rol' => 'Encargado']), $this->admin);

    expect($editado->name)->toBe('Ana María')->and($editado->nombreRol())->toBe('Encargado')
        ->and(Hash::check('clave-segura-1', $editado->refresh()->password))->toBeTrue();

    ($this->guardar)($u, datosUsuario(['password' => 'otra-clave-99']), $this->admin);
    expect(Hash::check('otra-clave-99', $u->refresh()->password))->toBeTrue();
});

it('desactivar a alguien le quita el acceso sin borrarlo', function () {
    $u = ($this->guardar)(null, datosUsuario(), $this->admin);
    ($this->guardar)($u, datosUsuario(['password' => '', 'activo' => false]), $this->admin);

    Volt::test('pages.auth.login')->set('form.login', 'ana')->set('form.password', 'clave-segura-1')->call('login')->assertHasErrors('form.login');
    expect(User::where('usuario', 'ana')->exists())->toBeTrue();
});

it('nadie puede desactivarse a sí mismo', function () {
    expect(fn () => ($this->guardar)($this->admin, datosUsuario(['usuario' => 'duena', 'name' => 'Dueña', 'password' => '', 'rol' => 'Administrador', 'activo' => false]), $this->admin))
        ->toThrow(ValidationException::class);
});

it('la tienda nunca se queda sin administrador activo', function () {
    // Único administrador: no se le puede bajar el rol
    expect(fn () => ($this->guardar)($this->admin, datosUsuario(['usuario' => 'duena', 'password' => '', 'rol' => 'Cajero']), $this->admin))->toThrow(ValidationException::class, 'al menos un administrador');

    // Con otro administrador activo sí se puede
    $otro = ($this->guardar)(null, datosUsuario(['usuario' => 'jefe2', 'rol' => 'Administrador']), $this->admin);
    ($this->guardar)($this->admin, datosUsuario(['usuario' => 'duena', 'password' => '', 'rol' => 'Cajero']), $otro);

    expect($this->admin->refresh()->nombreRol())->toBe('Cajero');
});

it('un administrador desactivado no cuenta como otro administrador', function () {
    $otro = ($this->guardar)(null, datosUsuario(['usuario' => 'jefe2', 'rol' => 'Administrador', 'activo' => false]), $this->admin);

    expect(fn () => ($this->guardar)($this->admin, datosUsuario(['usuario' => 'duena', 'password' => '', 'rol' => 'Encargado']), $this->admin))->toThrow(ValidationException::class);
});

it('sin permiso no se administran usuarios aunque se llame a la acción', function () {
    $cajero = User::factory()->conRol(Rol::Cajero)->create();

    expect(fn () => ($this->guardar)(null, datosUsuario(), $cajero))->toThrow(HttpException::class);
});

it('la pantalla crea y edita usuarios y muestra los errores', function () {
    $c = Volt::actingAs($this->admin)->test('pages.usuarios')
        ->call('nuevo')->set('name', 'Beto Ruiz')->set('usuario', 'beto')->set('password', 'clave-segura-1')->set('rol', 'Encargado')
        ->call('guardar')->assertHasNoErrors()->assertSee('Usuario «beto» creado');

    $beto = User::where('usuario', 'beto')->first();
    expect($beto->nombreRol())->toBe('Encargado');

    $c->call('editar', $beto->id)->assertSet('usuario', 'beto')->assertSet('password', '')
        ->set('usuario', 'duena')->call('guardar')->assertHasErrors('usuario');
});

it('cada permiso tiene una etiqueta en lenguaje sencillo', function () {
    foreach (Permiso::cases() as $permiso) {
        expect($permiso->etiqueta())->toBeString()->not->toBeEmpty()->not->toContain('.');
    }
});

it('la pantalla explica qué puede hacer cada rol', function () {
    Volt::actingAs($this->admin)->test('pages.usuarios')->assertSee('Cobrar en la caja')->assertSee('Cambiar la configuración de la tienda');
});
