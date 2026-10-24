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

    Route::post('logout', CerrarSesionController::class)->name('logout');
});

require __DIR__.'/auth.php';
