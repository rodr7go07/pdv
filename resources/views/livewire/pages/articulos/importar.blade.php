<?php

use App\Actions\Articulos\ImportarArticulos;
use App\Exports\PlantillaArticulos;
use App\Imports\LectorHoja;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:xlsx,xls,csv,txt|max:5120', as: 'archivo')]
    public $archivo = null;

    /** @var array{creados: int, actualizados: int, errores: array, sin_permiso_precios: bool}|null */
    public ?array $resultado = null;

    public ?string $fallo = null;

    public function mount(): void
    {
        $this->authorize('articulos.importar');
    }

    public function plantilla()
    {
        $this->authorize('articulos.importar');

        return Excel::download(new PlantillaArticulos, 'plantilla-articulos.xlsx');
    }

    public function updatedArchivo(): void
    {
        $this->resultado = null;
        $this->fallo = null;
    }

    public function importar(ImportarArticulos $importar): void
    {
        $this->authorize('articulos.importar');
        $this->validate();

        $this->resultado = null;
        $this->fallo = null;

        try {
            $filas = LectorHoja::leer($this->archivo->getRealPath());

            if ($filas === []) {
                $this->fallo = 'El archivo no tiene artículos para importar.';

                return;
            }

            $this->resultado = $importar($filas, auth()->user(), auth()->user()->can('articulos.editar_precio'));
        } catch (\InvalidArgumentException $e) {
            $this->fallo = $e->getMessage();
        }

        if ($this->resultado && $this->resultado['errores'] === []) {
            $this->reset('archivo');
        }
    }
}; ?>

<div>
    <x-slot name="titulo">Importar artículos</x-slot>
    <x-slot name="acciones">
        <x-enlace-volver :href="route('articulos.index')">Volver a la lista</x-enlace-volver>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-card titulo="1. Descarga la plantilla" descripcion="Trae ejemplos, instrucciones y la lista de departamentos, unidades e impuestos.">
            <x-secondary-button wire:click="plantilla" wire:loading.attr="disabled" class="w-full sm:w-auto">
                <x-phosphor-download-simple-bold class="size-4" /> Descargar plantilla de Excel
            </x-secondary-button>
            <ul class="mt-4 space-y-1.5 text-sm text-anil-700">
                <li>• Llénala con tus artículos (hasta 5,000 filas).</li>
                <li>• Si la clave ya existe, se actualiza ese artículo.</li>
                <li>• Si una fila tiene error, no se guarda ninguna: así puedes corregir y volver a subir.</li>
            </ul>
        </x-card>

        <x-card titulo="2. Sube tu archivo" descripcion="Excel (.xlsx) o CSV de hasta 5 MB.">
            <form wire:submit="importar" class="space-y-4">
                <div>
                    <input wire:model="archivo" id="archivo" type="file" accept=".xlsx,.xls,.csv"
                           class="block w-full cursor-pointer text-sm text-anil-800 file:mr-3 file:rounded-lg file:border-0 file:bg-anil-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-anil-800 hover:file:bg-anil-200">
                    <p class="mt-1.5 text-xs text-anil-600" wire:loading wire:target="archivo">Subiendo archivo…</p>
                    <x-input-error :messages="$errors->get('archivo')" class="mt-1.5" />
                </div>
                <x-primary-button wire:loading.attr="disabled" wire:target="importar,archivo" :disabled="! $archivo">
                    <span wire:loading.remove wire:target="importar">Importar artículos</span>
                    <span wire:loading wire:target="importar">Importando…</span>
                </x-primary-button>
            </form>
        </x-card>
    </div>

    @if ($fallo)
        <x-alerta tono="error" class="mt-6">{{ $fallo }}</x-alerta>
    @endif

    @if ($resultado)
        @if ($resultado['errores'] === [])
            <div class="mt-6 rounded-xl bg-emerald-50 px-5 py-4 text-emerald-900 ring-1 ring-emerald-200" role="status">
                <p class="flex items-center gap-2 font-extrabold"><x-phosphor-check-circle-bold class="size-5" /> Importación terminada</p>
                <p class="mt-1 text-sm">
                    {{ $resultado['creados'] }} {{ $resultado['creados'] === 1 ? 'artículo nuevo' : 'artículos nuevos' }} ·
                    {{ $resultado['actualizados'] }} {{ $resultado['actualizados'] === 1 ? 'actualizado' : 'actualizados' }}.
                </p>
                @if ($resultado['sin_permiso_precios'])
                    <p class="mt-2 text-sm font-medium">Aviso: no tienes permiso para cambiar costos y precios, así que esas columnas se ignoraron.</p>
                @endif
                <a href="{{ route('articulos.index') }}" wire:navigate class="mt-3 inline-block text-sm font-semibold underline">Ver los artículos</a>
            </div>
        @else
            <x-card :titulo="count($resultado['errores']).' '.(count($resultado['errores']) === 1 ? 'fila con error' : 'filas con error')"
                    descripcion="No se guardó nada. Corrige estas filas en tu archivo y vuelve a subirlo." class="mt-6">
                <div class="-mx-5 overflow-x-auto sm:-mx-6">
                    <table class="w-full min-w-[28rem] text-left text-sm">
                        <thead>
                            <tr class="rotulo border-b border-anil-100">
                                <th class="px-5 py-2 font-medium sm:px-6">Fila</th>
                                <th class="px-3 py-2 font-medium">Clave</th>
                                <th class="px-5 py-2 font-medium sm:px-6">Qué corregir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-anil-50">
                            @foreach ($resultado['errores'] as $error)
                                <tr>
                                    <td class="px-5 py-2.5 font-mono font-semibold sm:px-6">{{ $error['fila'] }}</td>
                                    <td class="px-3 py-2.5 font-mono text-anil-700">{{ $error['clave'] ?: '—' }}</td>
                                    <td class="px-5 py-2.5 text-red-800 sm:px-6">
                                        <ul class="space-y-0.5">
                                            @foreach ($error['mensajes'] as $mensaje)
                                                <li>{{ $mensaje }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @endif
</div>
