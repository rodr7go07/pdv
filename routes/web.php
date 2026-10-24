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

    // Inventario y compras (Fase 2)
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Volt::route('/', 'pages.inventario.index')->middleware('can:inventario.ver')->name('index');
        Volt::route('kardex/{articulo}', 'pages.inventario.kardex')->middleware('can:inventario.ver')->name('kardex');
        Volt::route('ajustes', 'pages.inventario.ajustes')->middleware('can:inventario.ajustar')->name('ajustes');
        Volt::route('conteos', 'pages.inventario.conteos')->middleware('can:inventario.conteo')->name('conteos');
        Volt::route('conteos/{conteo}', 'pages.inventario.conteo')->middleware('can:inventario.conteo')->name('conteo');
    });

    Route::prefix('compras')->name('compras.')->group(function () {
        Volt::route('/', 'pages.compras.index')->middleware('can:compras.ver')->name('index');
        Volt::route('crear', 'pages.compras.crear')->middleware('can:compras.registrar')->name('crear');
        Volt::route('{compra}', 'pages.compras.ver')->middleware('can:compras.ver')->name('ver');
    });

    Volt::route('proveedores', 'pages.proveedores')->middleware('can:proveedores.administrar')->name('proveedores');

    Route::view('guia', 'guia')->name('guia');

    Route::view('catalogos', 'catalogos')->middleware('can:catalogos.administrar')->name('catalogos');

    Route::post('logout', CerrarSesionController::class)->name('logout');
});

require __DIR__.'/auth.php';
