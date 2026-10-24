<?php

use App\Actions\Configuracion\ActualizarConfiguracion;
use App\Livewire\Forms\ConfiguracionForm;
use App\Models\Configuracion;
use Livewire\Attributes\Layout;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public ConfiguracionForm $form;

    #[Validate('nullable|image|mimes:png,jpg,jpeg,webp|max:1024', as: 'logotipo')]
    public $logo = null;

    public bool $quitarLogo = false;

    public ?string $logoActual = null;

    public function mount(): void
    {
        $this->authorize('configuracion.editar');

        $configuracion = Configuracion::actual();
        $this->form->cargar($configuracion);
        $this->logoActual = $configuracion->logoUrl();
    }

    public function guardar(ActualizarConfiguracion $actualizar): void
    {
        $this->authorize('configuracion.editar');

        $datos = $this->form->datos();
        $this->validateOnly('logo');

        $configuracion = $actualizar(Configuracion::actual(), $datos, $this->logo, $this->quitarLogo);

        $this->form->cargar($configuracion);
        $this->logoActual = $configuracion->logoUrl();
        $this->reset('logo', 'quitarLogo');

        $this->dispatch('configuracion-guardada');
    }

    public function updatedLogo(): void
    {
        try {
            $this->validateOnly('logo');
        } catch (ValidationException $e) {
            // Un archivo inválido no se conserva ni se intenta previsualizar.
            $this->reset('logo');

            throw $e;
        }

        $this->quitarLogo = false;
    }

    public function logoVista(): ?string
    {
        if ($this->logo) {
            return $this->logo->isPreviewable() ? $this->logo->temporaryUrl() : null;
        }

        return $this->quitarLogo ? null : $this->logoActual;
    }
}; ?>

<div>
    <x-slot name="titulo">Configuración</x-slot>

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_21rem]">
        {{-- Formulario --}}
        <form wire:submit="guardar" class="space-y-6 rounded-xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)] sm:p-6">
            <div>
                <h2 class="text-lg font-extrabold text-anil-900">Datos de la tienda</h2>
                <p class="mt-1 text-sm text-anil-700">Aparecen en el encabezado de cada ticket.</p>
            </div>

            <div>
                <x-input-label for="nombre_tienda" value="Nombre de la tienda" />
                <x-text-input wire:model.live.debounce.300ms="form.nombre_tienda" id="nombre_tienda" type="text" required maxlength="80" />
                <x-input-error :messages="$errors->get('form.nombre_tienda')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_13rem]">
                <div>
                    <x-input-label for="razon_social" value="Razón social" />
                    <x-text-input wire:model.live.debounce.300ms="form.razon_social" id="razon_social" type="text" maxlength="150" />
                    <x-input-error :messages="$errors->get('form.razon_social')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="rfc" value="RFC" />
                    <x-text-input wire:model.live.debounce.300ms="form.rfc" id="rfc" type="text" maxlength="13"
                                  class="font-mono uppercase" autocapitalize="characters" spellcheck="false" />
                    <x-input-error :messages="$errors->get('form.rfc')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="direccion" value="Dirección" />
                <x-text-input wire:model.live.debounce.300ms="form.direccion" id="direccion" type="text" maxlength="255" autocomplete="street-address" />
                <x-input-error :messages="$errors->get('form.direccion')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="telefono" value="Teléfono" />
                <x-text-input wire:model.live.debounce.300ms="form.telefono" id="telefono" type="tel" maxlength="20" inputmode="tel" autocomplete="tel" />
                <x-input-error :messages="$errors->get('form.telefono')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="leyenda_ticket" value="Leyenda al pie del ticket" />
                <x-text-input wire:model.live.debounce.300ms="form.leyenda_ticket" id="leyenda_ticket" type="text" maxlength="255"
                              placeholder="Gracias por su compra" />
                <x-input-error :messages="$errors->get('form.leyenda_ticket')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="logo" value="Logotipo" />
                <div class="flex flex-wrap items-center gap-4">
                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-dashed border-anil-300 bg-papel">
                        @if ($this->logoVista())
                            <img src="{{ $this->logoVista() }}" alt="Logotipo actual" class="size-full object-contain p-1">
                        @else
                            <x-phosphor-image-bold class="size-6 text-anil-300" />
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 space-y-2">
                        <input wire:model="logo" id="logo" type="file" accept="image/png,image/jpeg,image/webp"
                               class="block w-full cursor-pointer text-sm text-anil-800 file:mr-3 file:rounded-lg file:border-0 file:bg-anil-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-anil-800 hover:file:bg-anil-200">
                        <p class="text-xs text-anil-600" wire:loading wire:target="logo">Subiendo imagen…</p>
                        @if ($logoActual && ! $logo)
                            <label class="flex min-h-9 items-center gap-2 text-sm text-anil-800">
                                <input wire:model.live="quitarLogo" type="checkbox" class="size-4 rounded border-anil-300 text-rosa-500 focus:ring-rosa-500/30">
                                Quitar el logotipo actual
                            </label>
                        @endif
                    </div>
                </div>
                <p class="mt-2 text-xs text-anil-600">PNG, JPG o WebP de hasta 1 MB. Mejor si es cuadrado y en blanco y negro.</p>
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4 border-t border-anil-100 pt-5">
                <x-primary-button wire:loading.attr="disabled" wire:target="guardar,logo">Guardar cambios</x-primary-button>
                <x-action-message on="configuracion-guardada" />
            </div>
        </form>

        {{-- Vista previa del ticket --}}
        <aside class="mx-auto w-full max-w-sm xl:sticky xl:top-8 xl:mx-0" aria-label="Vista previa del ticket">
            <p class="rotulo mb-3">Vista previa del ticket</p>

            <div class="ticket px-6 pt-7 font-mono text-[0.8125rem] leading-snug text-tinta">
                <div class="flex flex-col items-center text-center">
                    @if ($this->logoVista())
                        <img src="{{ $this->logoVista() }}" alt="" class="mb-2 size-14 object-contain grayscale">
                    @endif
                    <p class="text-base font-medium uppercase tracking-wide">{{ $form->nombre_tienda ?: 'Nombre de la tienda' }}</p>
                    @if ($form->razon_social)
                        <p class="mt-1">{{ $form->razon_social }}</p>
                    @endif
                    @if ($form->rfc)
                        <p>RFC {{ mb_strtoupper($form->rfc) }}</p>
                    @endif
                    @if ($form->direccion)
                        <p class="mt-1 text-anil-700">{{ $form->direccion }}</p>
                    @endif
                    @if ($form->telefono)
                        <p class="text-anil-700">Tel. {{ $form->telefono }}</p>
                    @endif
                </div>

                <hr class="corte my-4">

                <div class="space-y-1.5 text-anil-400" aria-hidden="true">
                    <div class="flex justify-between"><span>Artículos</span><span>···</span></div>
                    <div class="flex justify-between"><span>Total</span><span>$ ···</span></div>
                </div>

                <hr class="corte my-4">

                <p class="text-center">{{ $form->leyenda_ticket ?: 'Gracias por su compra' }}</p>
            </div>
        </aside>
    </div>
</div>
