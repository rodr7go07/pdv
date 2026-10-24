<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

it('muestra la página de mi cuenta', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertOk()
        ->assertSee('Datos personales')
        ->assertSee('Cambiar contraseña');
});

it('actualiza el nombre y el correo', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-profile-information-form')
        ->set('name', 'Nombre Nuevo')
        ->set('email', 'nuevo@tienda.test')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->name)->toBe('Nombre Nuevo')
        ->and($user->email)->toBe('nuevo@tienda.test');
});

it('permite dejar el correo vacío', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-profile-information-form')
        ->set('email', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

it('no permite usar el correo de otro usuario', function () {
    User::factory()->create(['email' => 'ocupado@tienda.test']);
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-profile-information-form')
        ->set('email', 'ocupado@tienda.test')
        ->call('updateProfileInformation')
        ->assertHasErrors('email');
});

it('no deja cambiar el nombre de usuario desde la cuenta', function () {
    $user = User::factory()->create(['usuario' => 'maria']);

    Volt::actingAs($user)->test('profile.update-profile-information-form')
        ->set('name', 'Otro Nombre')
        ->call('updateProfileInformation');

    expect($user->refresh()->usuario)->toBe('maria');
});

it('cambia la contraseña con la contraseña actual correcta', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'clave-nueva-123')
        ->set('password_confirmation', 'clave-nueva-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('clave-nueva-123', $user->refresh()->password))->toBeTrue();
});

it('rechaza el cambio si la contraseña actual es incorrecta', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-password-form')
        ->set('current_password', 'equivocada')
        ->set('password', 'clave-nueva-123')
        ->set('password_confirmation', 'clave-nueva-123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('exige que la confirmación coincida y tenga 8 caracteres', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'corta')
        ->set('password_confirmation', 'distinta')
        ->call('updatePassword')
        ->assertHasErrors('password');
});
