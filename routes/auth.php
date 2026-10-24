<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Sin registro público: los usuarios los da de alta el administrador.
Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');
});
