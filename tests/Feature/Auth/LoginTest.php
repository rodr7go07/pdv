<?php

use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Livewire\Volt\Volt;

beforeEach(fn () => $this->seed(RolesYPermisosSeeder::class));

it('muestra la pantalla de acceso', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Acceso al sistema')
        ->assertSee('Usuario o correo');
});

it('permite entrar con el nombre de usuario', function () {
    $user = User::factory()->create(['usuario' => 'maria', 'email' => null]);

    Volt::test('pages.auth.login')
        ->set('form.login', 'maria')
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('permite entrar con el correo', function () {
    $user = User::factory()->create(['email' => 'maria@tienda.test']);

    Volt::test('pages.auth.login')
        ->set('form.login', 'maria@tienda.test')
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('rechaza una contraseña incorrecta', function () {
    User::factory()->create(['usuario' => 'maria']);

    Volt::test('pages.auth.login')
        ->set('form.login', 'maria')
        ->set('form.password', 'incorrecta')
        ->call('login')
        ->assertHasErrors('form.login');

    $this->assertGuest();
});

it('no deja entrar a un usuario inactivo', function () {
    User::factory()->inactivo()->create(['usuario' => 'baja']);

    Volt::test('pages.auth.login')
        ->set('form.login', 'baja')
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors('form.login');

    $this->assertGuest();
});

it('cierra la sesión de un usuario desactivado mientras estaba dentro', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['activo' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('bloquea temporalmente tras demasiados intentos fallidos', function () {
    User::factory()->create(['usuario' => 'maria']);

    $intento = fn () => Volt::test('pages.auth.login')
        ->set('form.login', 'maria')
        ->set('form.password', 'mal')
        ->call('login');

    foreach (range(1, 5) as $_) {
        $intento();
    }

    $intento()->assertHasErrors('form.login');
    $this->assertGuest();
});

it('no tiene registro público', function () {
    $this->get('/register')->assertNotFound();
});

it('redirige a los visitantes al acceso', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/configuracion')->assertRedirect('/login');
});

it('cierra la sesión', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});
