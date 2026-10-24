<?php

use App\Http\Controllers\CajaPruebaController;
use App\Http\Controllers\CerrarSesionController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\EstadoCuentaController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReporteExportController;
use App\Http\Controllers\VentaTicketController;
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
        Volt::route('etiquetas', 'pages.articulos.etiquetas')->middleware('can:articulos.ver')->name('etiquetas');
        Volt::route('{articulo}/editar', 'pages.articulos.formulario')->middleware('can:articulos.editar')->name('editar');
    });

    // Punto de venta (Fase 3)
    Volt::route('venta', 'pages.pos')->middleware('can:ventas.cobrar')->name('pos');
    Route::get('venta/buscar', [PosController::class, 'buscar'])->middleware('can:ventas.cobrar')->name('pos.buscar');
    Route::get('venta/por-ids', [PosController::class, 'porIds'])->middleware('can:ventas.cobrar')->name('pos.por-ids');

    Route::prefix('ventas')->name('ventas.')->middleware('permission:ventas.cobrar|ventas.ver_todas')->group(function () {
        Volt::route('/', 'pages.ventas.index')->name('index');
        Volt::route('{venta}', 'pages.ventas.ver')->name('ver');
        Route::get('{venta}/ticket', VentaTicketController::class)->name('ticket');
    });

    // Reportes y tablero (Fase 7)
    Volt::route('reportes', 'pages.reportes.index')->middleware('can:reportes.ver')->name('reportes');
    Route::get('reportes/exportar/{seccion}', ReporteExportController::class)->middleware('can:reportes.ver')->name('reportes.exportar');

    // Clientes y crédito (Fase 6)
    Route::prefix('clientes')->name('clientes.')->middleware('can:clientes.ver')->group(function () {
        Volt::route('/', 'pages.clientes.index')->name('index');
        Volt::route('{cliente}', 'pages.clientes.cuenta')->name('cuenta');
        Route::get('{cliente}/estado-de-cuenta', EstadoCuentaController::class)->name('estado');
    });

    // Caja y cortes (Fase 4)
    Route::prefix('caja')->name('caja')->group(function () {
        Volt::route('/', 'pages.caja.index')->middleware('permission:caja.abrir|caja.corte')->name('');
        Volt::route('turnos', 'pages.caja.turnos')->middleware('can:caja.supervisar')->name('.turnos');
        Volt::route('turnos/{turno}', 'pages.caja.turno')->name('.turno');
        Route::get('cortes/{corte}', CorteController::class)->name('.corte');
    });
    Volt::route('cajas', 'pages.cajas')->middleware('can:cajas.administrar')->name('cajas');
    Route::get('cajas/{caja}/prueba', CajaPruebaController::class)->middleware('can:cajas.administrar')->name('cajas.prueba');

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
