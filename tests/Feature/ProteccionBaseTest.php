<?php

use App\Support\ProteccionBase;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;

afterEach(function () {
    FreshCommand::prohibit(false);
    WipeCommand::prohibit(false);
});

it('bloquea borrar la base en MySQL y en producción, pero no en la SQLite de pruebas', function () {
    expect(ProteccionBase::bloquear())->toBeFalse(); // las pruebas corren en SQLite

    config(['database.default' => 'mysql']);
    expect(ProteccionBase::bloquear())->toBeTrue();

    config(['database.permitir_borrar' => true]);
    expect(ProteccionBase::bloquear())->toBeFalse();

    config(['database.default' => 'sqlite', 'database.permitir_borrar' => false]);
    app()->detectEnvironment(fn () => 'production');
    expect(ProteccionBase::bloquear())->toBeTrue();
    app()->detectEnvironment(fn () => 'testing');
});

it('con el candado puesto, migrate:fresh y db:wipe se niegan', function () {
    FreshCommand::prohibit(true);
    WipeCommand::prohibit(true);

    $this->artisan('migrate:fresh', ['--force' => true])->assertFailed();
    $this->artisan('db:wipe', ['--force' => true])->assertFailed();
});
