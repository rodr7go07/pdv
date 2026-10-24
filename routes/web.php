<?php

use App\Http\Controllers\CerrarSesionController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'activo'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    Volt::route('configuracion', 'pages.configuracion')
        ->middleware('can:configuracion.editar')
        ->name('configuracion');

    // Catálogo de artículos (Fase 1)
    Route::prefix('articulos')->name('articulos.')->group(function () {
        Volt::route('/', 'pages.articulos.index')->middleware('can:articulos.ver')->name('index');
        Volt::route('crear', 'pages.articulos.formulario')->middleware('can:articulos.crear')->name('crear');
        Volt::route('importar', 'pages.articulos.importar')->middleware('can:articulos.importar')->name('importar');
        Volt::route('{articulo}/editar', 'pages.articulos.formulario')->middleware('can:articulos.editar')->name('editar');
    });

    Route::view('catalogos', 'catalogos')->middleware('can:catalogos.administrar')->name('catalogos');

    Route::post('logout', CerrarSesionController::class)->name('logout');
});

require __DIR__.'/auth.php';
