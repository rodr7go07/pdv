<?php

use App\Actions\Usuarios\EliminarRol;
use App\Actions\Usuarios\GuardarRol;
use App\Actions\Usuarios\GuardarUsuario;
use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\User;
use App\Support\Menu;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->admin = User::factory()->conRol(Rol::Administrador)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->guardar = app(GuardarRol::class);
});

// ─── Acciones ────────────────────────────────────────────────────────────────

it('crea un rol con los permisos elegidos y quien lo tiene los usa', function () {
    $rol = ($this->guardar)(null, '  Almacenista ', ['inventario.ver', 'inventario.ajustar', 'compras.ver'], $this->admin);

    expect($rol->name)->toBe('Almacenista')->and($rol->permissions->pluck('name')->all())->toEqualCanonicalizing(['inventario.ver', 'inventario.ajustar', 'compras.ver']);

    $u = User::factory()->create();
    $u->assignRole('Almacenista');

    expect($u->can('inventario.ajustar'))->toBeTrue()->and($u->can('ventas.cancelar'))->toBeFalse();
});

it('editar un rol cambia de inmediato lo que pueden hacer sus usuarios', function () {
    $rol = ($this->guardar)(null, 'Supervisor', ['ventas.ver_todas'], $this->admin);
    $u = User::factory()->create();
    $u->assignRole($rol);
    expect($u->can('ventas.cancelar'))->toBeFalse();

    ($this->guardar)($rol, 'Supervisor de piso', ['ventas.ver_todas', 'ventas.cancelar'], $this->admin);

    expect($u->fresh()->can('ventas.cancelar'))->toBeTrue()->and($u->fresh()->nombreRol())->toBe('Supervisor de piso');
});

it('valida el nombre y los permisos', function () {
    foreach ([['', []], ['A', []], [str_repeat('x', 41), []], ['Cajero', []], ['Nuevo', ['no.existe']]] as [$nombre, $permisos]) {
        expect(fn () => ($this->guardar)(null, $nombre, $permisos, $this->admin))->toThrow(ValidationException::class);
    }

    expect(Role::count())->toBe(3);
});

it('el Administrador no se edita, y Encargado y Cajero cambian de permisos pero no de nombre', function () {
    expect(fn () => ($this->guardar)(Role::findByName('Administrador'), 'Administrador', [], $this->admin))->toThrow(ValidationException::class, 'siempre tiene todos');

    $cajero = Role::findByName('Cajero');
    expect(fn () => ($this->guardar)($cajero, 'Cajera', ['ventas.cobrar'], $this->admin))->toThrow(ValidationException::class, 'no se pueden renombrar');

    ($this->guardar)($cajero, 'Cajero', ['ventas.cobrar', 'reportes.ver'], $this->admin);
    expect(Role::findByName('Cajero')->permissions->pluck('name')->all())->toEqualCanonicalizing(['ventas.cobrar', 'reportes.ver']);
});

it('no se puede dar un permiso que uno no tiene ni administrar roles sin el permiso', function () {
    $gestor = ($this->guardar)(null, 'Gestor de roles', ['roles.administrar', 'ventas.cobrar'], $this->admin);
    $u = User::factory()->create();
    $u->assignRole($gestor);

    // Tiene roles.administrar pero no ventas.cancelar: no puede regalarlo.
    expect(fn () => ($this->guardar)(null, 'Propio', ['ventas.cancelar'], $u))->toThrow(ValidationException::class, 'No puedes dar permisos que tú no tienes');
    ($this->guardar)(null, 'Propio', ['ventas.cobrar'], $u); // lo que sí tiene, sí

    expect(fn () => ($this->guardar)(null, 'X1', [], $this->encargado))->toThrow(HttpException::class);
});

it('borra un rol propio sin usuarios y no los del sistema ni los que alguien tiene', function () {
    $eliminar = app(EliminarRol::class);
    $rol = ($this->guardar)(null, 'Temporal', ['ventas.cobrar'], $this->admin);
    $u = User::factory()->create();
    $u->assignRole($rol);

    expect(fn () => $eliminar($rol, $this->admin))->toThrow(ValidationException::class, 'lo tienen 1 usuario');
    expect(fn () => $eliminar(Role::findByName('Cajero'), $this->admin))->toThrow(ValidationException::class, 'no se pueden borrar');

    $u->removeRole('Temporal');
    $eliminar($rol, $this->admin);

    expect(Role::where('name', 'Temporal')->exists())->toBeFalse();
    expect(fn () => $eliminar(($this->guardar)(null, 'Otro', [], $this->admin), $this->encargado))->toThrow(HttpException::class);
});

it('Usuarios acepta los roles de la base de datos y rechaza los que no existen', function () {
    ($this->guardar)(null, 'Almacenista', ['inventario.ver'], $this->admin);
    $guardarUsuario = app(GuardarUsuario::class);

    $u = $guardarUsuario(null, ['name' => 'Ana', 'usuario' => 'ana', 'password' => 'clave-segura-1', 'rol' => 'Almacenista'], $this->admin);
    expect($u->nombreRol())->toBe('Almacenista');

    expect(fn () => $guardarUsuario(null, ['name' => 'Beto', 'usuario' => 'beto', 'password' => 'clave-segura-1', 'rol' => 'Inventado'], $this->admin))
        ->toThrow(ValidationException::class, 'Elige un rol de la lista');
});

// ─── Pantalla ────────────────────────────────────────────────────────────────

it('solo quien administra roles entra a la pantalla', function () {
    $this->actingAs($this->encargado)->get('/roles')->assertForbidden();
    $this->actingAs($this->admin)->get('/roles')->assertOk()->assertSee('Roles y permisos')->assertSee('Todos los permisos, siempre');
    expect(Menu::para($this->admin)->pluck('etiqueta')->all())->toContain('Roles')
        ->and(Menu::para($this->encargado)->pluck('etiqueta')->all())->not->toContain('Roles');
});

it('crea, edita y borra un rol desde la pantalla', function () {
    $pantalla = Volt::actingAs($this->admin)->test('pages.roles')
        ->call('nuevo')->set('nombre', 'Almacenista')->set('permisos', ['inventario.ver', 'compras.ver'])->call('guardar')->assertHasNoErrors()
        ->assertSee('Almacenista')->assertSee('Rol «Almacenista» creado.');

    $rol = Role::findByName('Almacenista');
    expect($rol->permissions)->toHaveCount(2);

    $pantalla->call('editar', $rol->id)->assertSet('nombre', 'Almacenista')->assertSet('permisos', ['inventario.ver', 'compras.ver'])
        ->set('permisos', ['inventario.ver'])->call('guardar')->assertHasNoErrors();
    expect($rol->fresh()->permissions)->toHaveCount(1);

    $pantalla->call('confirmarBorrado', $rol->id)->call('borrar')->assertHasNoErrors()->assertSee('Rol «Almacenista» borrado.');
    expect(Role::where('name', 'Almacenista')->exists())->toBeFalse();
});

it('la pantalla muestra los errores y no abre el rol Administrador', function () {
    Volt::actingAs($this->admin)->test('pages.roles')
        ->call('nuevo')->set('nombre', 'Cajero')->call('guardar')->assertHasErrors('nombre')
        ->call('editar', Role::findByName('Administrador')->id)->assertSet('editandoId', null);
});

it('un rol base se puede volver a sus permisos de fábrica', function () {
    $cajero = Role::findByName('Cajero');
    $cajero->syncPermissions(['ventas.cobrar']);

    Volt::actingAs($this->admin)->test('pages.roles')
        ->call('editar', $cajero->id)->assertSet('permisos', ['ventas.cobrar'])
        ->call('restablecer')
        ->assertSet('permisos', array_map(fn (Permiso $p) => $p->value, Rol::Cajero->permisos()));
});

it('la pantalla de Usuarios ofrece los roles creados', function () {
    ($this->guardar)(null, 'Almacenista', ['inventario.ver'], $this->admin);

    Volt::actingAs($this->admin)->test('pages.usuarios')->assertSee('Almacenista')->assertSee('Crear o cambiar roles');
});
