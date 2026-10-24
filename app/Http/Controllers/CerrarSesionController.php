<?php

namespace App\Http\Controllers;

use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;

class CerrarSesionController extends Controller
{
    public function __invoke(Logout $logout): RedirectResponse
    {
        $logout();

        return redirect()->route('login');
    }
}
