<?php

use App\Actions\Articulos\GuardarArticulo;
use App\Enums\TipoImpuesto;
use App\Livewire\Forms\ArticuloForm;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use App\Services\CalculadoraPrecios;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public ArticuloForm $form;

    public ?Articulo $articulo = null;

    #[Validate('nullable|image|mimes:png,jpg,jpeg,webp|max:2048', as: 'imagen')]
    public $imagen = null;

    public bool $quitarImagen = false;

    public ?string $imagenActual = null;

    public function mount(?Articulo $articulo = null): void
    {
        if ($articulo?->exists) {
            $this->authorize('articulos.editar');
            $this->articulo = $articulo;
            $this->form->cargar($articulo);
            $this->imagenActual = $articulo->imagenUrl();

            return;
        }

        $this->authorize('articulos.crear');
        $this->form->iva_id = (string) (Impuesto::query()->activos()->where('tipo', TipoImpuesto::Iva)->where('tasa', 16)->value('id') ?? '');
        $this->form->departamento_id = (string) (Departamento::query()->activos()->orderBy('nombre')->value('id') ?? '');
        $this->form->unidad_medida_id = (string) (UnidadMedida::query()->activas()->orderBy('id')->value('id') ?? '');
    }

    #[\Livewire\Attributes\Computed]
    public function departamentos()
    {
        return Departamento::query()->activos()->orderBy('nombre')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function unidades()
    {
        return UnidadMedida::query()->activas()->orderBy('nombre')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function impuestosIva()
    {
        return Impuesto::query()->activos()->where('tipo', TipoImpuesto::Iva)->orderBy('nombre')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function impuestosIeps()
    {
        return Impuesto::query()->activos()->where('tipo', TipoImpuesto::Ieps)->orderBy('nombre')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function puedePrecios(): bool
    {
        return auth()->user()->can('articulos.editar_precio');
    }

    /** Suma de las tasas de los impuestos elegidos, en porcentaje. */
    public function tasaActual(): string
    {
        $ids = array_filter([$this->form->iva_id, ...$this->form->ieps]);

        if ($ids === []) {
            return '0';
        }

        return CalculadoraPrecios::sumarTasas(
            Impuesto::query()->whereIn('id', $ids)->get()->map(fn (Impuesto $i) => $i->tasaAplicable())->all(),
        );
    }

    public function precioSinImpuestos(): ?string
    {
        if ($this->form->precio_1 === '' || ! is_numeric($this->form->precio_1)) {
            return null;
        }

        return CalculadoraPrecios::precioSinImpuestos($this->form->precio_1, $this->tasaActual());
    }

    /**
     * Costo, margen y precio se mantienen enlazados: lo último que se edita manda
     * y el resto se sugiere (el usuario siempre puede corregirlo).
     */
    public function updated(string $nombre): void
    {
        $f = $this->form;
        $numero = fn (string $v) => $v !== '' && is_numeric($v);

        match ($nombre) {
            'form.costo' => $numero($f->margen) && $numero($f->costo)
                ? $f->precio_1 = CalculadoraPrecios::precioDesdeMargen($f->costo, $f->margen, $this->tasaActual())
                : $this->recalcularMargen(),
            'form.margen' => $numero($f->margen) && $numero($f->costo)
                ? $f->precio_1 = CalculadoraPrecios::precioDesdeMargen($f->costo, $f->margen, $this->tasaActual())
                : null,
            'form.precio_1', 'form.iva_id', 'form.ieps' => $this->recalcularMargen(),
            default => null,
        };
    }

    private function recalcularMargen(): void
    {
        $f = $this->form;

        $f->margen = $f->costo !== '' && is_numeric($f->costo) && is_numeric($f->precio_1)
            ? (string) (CalculadoraPrecios::margenDesdePrecio($f->costo, $f->precio_1, $this->tasaActual()) ?? '')
            : '';
    }

    public function updatedImagen(): void
    {
        try {
            $this->validateOnly('imagen');
        } catch (ValidationException $e) {
            $this->reset('imagen');

            throw $e;
        }

        $this->quitarImagen = false;
    }

    public function imagenVista(): ?string
    {
        if ($this->imagen) {
            return $this->imagen->isPreviewable() ? $this->imagen->temporaryUrl() : null;
        }

        return $this->quitarImagen ? null : $this->imagenActual;
    }

    public function guardar(GuardarArticulo $guardar): void
    {
        $this->authorize($this->articulo ? 'articulos.editar' : 'articulos.crear');
        $this->validateOnly('imagen');

        try {
            $guardado = $guardar(
                $this->articulo,
                $this->form->datos(),
                auth()->user(),
                $this->puedePrecios,
                $this->imagen,
                $this->quitarImagen,
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($mensajes, $campo) => [
                    match (true) {
                        $campo === 'impuestos' || str_starts_with($campo, 'impuestos.') => 'form.iva_id',
                        default => 'form.'.$campo,
                    } => $mensajes,
                ])->all(),
            );
        }

        if (! $this->articulo) {
            session()->flash('exito', "Artículo «{$guardado->descripcion}» creado.");
            $this->redirectRoute('articulos.editar', $guardado, navigate: true);

            return;
        }

        $this->articulo = $guardado;
        $this->form->cargar($guardado);
        $this->imagenActual = $guardado->imagenUrl();
        $this->reset('imagen', 'quitarImagen');
        $this->dispatch('articulo-guardado');
    }

    public function eliminar(): void
    {
        $this->authorize('articulos.editar');
        abort_unless($this->articulo, 404);

        $nombre = $this->articulo->descripcion;
        $this->articulo->delete();

        session()->flash('exito', "Artículo «{$nombre}» eliminado. Su clave queda reservada.");
        $this->redirectRoute('articulos.index', navigate: true);
    }

    #[\Livewire\Attributes\Computed]
    public function historial()
    {
        return $this->articulo
            ? $this->articulo->historialPrecios()->with('usuario:id,name')->limit(30)->get()
            : collect();
    }
}; ?>

<div>
    <x-slot name="titulo">{{ $articulo ? 'Editar artículo' : 'Nuevo artículo' }}</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('articulos.index') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Volver a la lista
        </a>
    </x-slot>

    @if (session('exito'))
        <div class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ session('exito') }}
        </div>
    @endif

    <form wire:submit="guardar" class="space-y-6 pb-24 lg:pb-0">
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                {{-- Identificación --}}
                <x-card titulo="Identificación" descripcion="Cómo se llama el artículo y con qué códigos se encuentra.">
                    <div class="space-y-5">
                        <div class="grid gap-5 sm:grid-cols-[14rem_minmax(0,1fr)]">
                            <div>
                                <x-input-label for="clave" value="Clave principal" />
                                <x-text-input wire:model="form.clave" id="clave" type="text" required maxlength="40"
                                              class="font-mono" autocomplete="off" spellcheck="false" autofocus
                                              x-on:keydown.enter.prevent="$refs.descripcion.focus()" />
                                <p class="mt-1 text-xs text-anil-600">Escanea el código de barras aquí o escríbelo.</p>
                                <x-input-error :messages="$errors->get('form.clave')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="descripcion" value="Descripción" />
                                <x-text-input wire:model="form.descripcion" x-ref="descripcion" id="descripcion" type="text" required maxlength="150" autocomplete="off" />
                                <x-input-error :messages="$errors->get('form.descripcion')" class="mt-1.5" />
                            </div>
                        </div>

                        {{-- Claves alternas --}}
                        <div x-data="{
                                nueva: '',
                                agregar() {
                                    const c = this.nueva.trim();
                                    if (c && ! $wire.form.claves_alternas.includes(c)) { $wire.form.claves_alternas.push(c); }
                                    this.nueva = '';
                                },
                                quitar(i) { $wire.form.claves_alternas.splice(i, 1); },
                            }">
                            <x-input-label for="nueva_alterna" value="Claves alternas (opcional)" />
                            <div class="flex gap-2">
                                <x-text-input x-model="nueva" id="nueva_alterna" type="text" maxlength="40" class="font-mono" autocomplete="off" spellcheck="false"
                                              placeholder="Otro código del mismo artículo"
                                              x-on:keydown.enter.prevent="agregar()" />
                                <x-secondary-button x-on:click="agregar()" class="shrink-0">Agregar</x-secondary-button>
                            </div>
                            <ul class="mt-3 flex flex-wrap gap-2" x-show="$wire.form.claves_alternas.length" x-cloak>
                                <template x-for="(clave, i) in $wire.form.claves_alternas" :key="clave">
                                    <li class="inline-flex items-center gap-1 rounded-lg bg-anil-50 py-1 pl-3 pr-1 font-mono text-sm text-anil-900 ring-1 ring-anil-100">
                                        <span x-text="clave"></span>
                                        <button type="button" @click="quitar(i)" class="grid size-8 place-items-center rounded-md text-anil-600 hover:bg-anil-100" :aria-label="'Quitar ' + clave">
                                            <x-phosphor-x-bold class="size-3.5" />
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            @foreach ($errors->get('form.claves_alternas.*') as $mensajes)
                                <x-input-error :messages="$mensajes" class="mt-1.5" />
                            @endforeach
                            <x-input-error :messages="$errors->get('form.claves_alternas')" class="mt-1.5" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="departamento_id" value="Departamento" />
                                <x-select wire:model="form.departamento_id" id="departamento_id" required>
                                    <option value="">Elige uno…</option>
                                    @foreach ($this->departamentos as $d)
                                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                    @endforeach
                                </x-select>
                                <x-input-error :messages="$errors->get('form.departamento_id')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="unidad_medida_id" value="Unidad de medida" />
                                <x-select wire:model="form.unidad_medida_id" id="unidad_medida_id" required>
                                    <option value="">Elige una…</option>
                                    @foreach ($this->unidades as $u)
                                        <option value="{{ $u->id }}">{{ $u->nombre }} ({{ $u->abreviatura }})</option>
                                    @endforeach
                                </x-select>
                                <x-input-error :messages="$errors->get('form.unidad_medida_id')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>
                </x-card>

                {{-- Precios --}}
                <x-card titulo="Costo y precios" descripcion="Los precios de venta ya incluyen los impuestos. El costo va sin impuestos.">
                    @if (! $this->puedePrecios)
                        <p class="mb-4 flex items-start gap-2 rounded-lg bg-etiqueta/25 px-3 py-2.5 text-sm text-amber-900">
                            <x-phosphor-lock-bold class="mt-0.5 size-4 shrink-0" />
                            No tienes permiso para cambiar costos ni precios. Puedes editar el resto del artículo.
                        </p>
                    @endif

                    <div class="space-y-5">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <x-input-label for="costo" value="Costo (sin impuestos)" />
                                <x-text-input wire:model.blur="form.costo" id="costo" type="text" inputmode="decimal" class="font-mono" :disabled="! $this->puedePrecios" placeholder="0.00" />
                                <x-input-error :messages="$errors->get('form.costo')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="margen" value="Margen sobre el costo (%)" />
                                <x-text-input wire:model.blur="form.margen" id="margen" type="text" inputmode="decimal" class="font-mono" :disabled="! $this->puedePrecios" placeholder="30" />
                            </div>
                            <div>
                                <x-input-label for="precio_1" value="Precio 1 (al público)" />
                                <x-text-input wire:model.blur="form.precio_1" id="precio_1" type="text" inputmode="decimal" required class="font-mono font-semibold" :disabled="! $this->puedePrecios" placeholder="0.00" />
                                <x-input-error :messages="$errors->get('form.precio_1')" class="mt-1.5" />
                            </div>
                        </div>

                        @if ($this->precioSinImpuestos() !== null)
                            <p class="text-sm text-anil-700">
                                Sin impuestos el precio 1 queda en
                                <strong class="font-mono text-anil-900">${{ number_format((float) $this->precioSinImpuestos(), 2) }}</strong>.
                            </p>
                        @endif

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="precio_2" value="Precio 2 · mayoreo (opcional)" />
                                <x-text-input wire:model="form.precio_2" id="precio_2" type="text" inputmode="decimal" class="font-mono" :disabled="! $this->puedePrecios" />
                                <x-input-error :messages="$errors->get('form.precio_2')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="precio_3" value="Precio 3 · mayoreo (opcional)" />
                                <x-text-input wire:model="form.precio_3" id="precio_3" type="text" inputmode="decimal" class="font-mono" :disabled="! $this->puedePrecios" />
                                <x-input-error :messages="$errors->get('form.precio_3')" class="mt-1.5" />
                            </div>
                        </div>

                        <div class="grid gap-5 border-t border-anil-50 pt-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="iva_id" value="IVA" />
                                <x-select wire:model.live="form.iva_id" id="iva_id">
                                    <option value="">Sin IVA</option>
                                    @foreach ($this->impuestosIva as $i)
                                        <option value="{{ $i->id }}">{{ $i->nombre }}</option>
                                    @endforeach
                                </x-select>
                                <x-input-error :messages="$errors->get('form.iva_id')" class="mt-1.5" />
                            </div>
                            @if ($this->impuestosIeps->isNotEmpty())
                                <fieldset>
                                    <legend class="rotulo mb-1.5">IEPS</legend>
                                    @foreach ($this->impuestosIeps as $i)
                                        <x-checkbox wire:model.live="form.ieps" value="{{ $i->id }}" :label="$i->nombre" />
                                    @endforeach
                                </fieldset>
                            @endif
                        </div>
                    </div>
                </x-card>

                {{-- Inventario --}}
                <x-card titulo="Inventario" descripcion="Cómo se controla la existencia de este artículo.">
                    @if ($articulo && $articulo->inventariable)
                        <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-anil-50 px-4 py-3 text-sm text-anil-800">
                            <span>Existencia actual:
                                <strong class="font-mono text-base text-anil-900">{{ rtrim(rtrim((string) $articulo->existencia, '0'), '.') ?: '0' }} {{ $articulo->unidad->abreviatura }}</strong>
                            </span>
                            <span class="text-anil-600">Cambia con compras, ventas, ajustes o conteos.</span>
                            @can('inventario.ver')
                                <a href="{{ route('inventario.kardex', $articulo) }}" wire:navigate class="font-semibold text-rosa-600 underline">Ver kardex</a>
                            @endcan
                        </p>
                    @endif

                    <div class="space-y-2">
                        <x-checkbox wire:model.live="form.inventariable" label="Llevar control de inventario" ayuda="Desmárcalo para servicios o artículos que no se cuentan." />
                        <x-checkbox wire:model="form.granel" label="Se vende a granel" ayuda="Se pesa o se mide al vender, por ejemplo por kilo. La unidad debe permitir decimales." />
                        <x-input-error :messages="$errors->get('form.unidad_medida_id')" />
                    </div>

                    @if ($form->inventariable)
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="existencia_minima" value="Existencia mínima" />
                                <x-text-input wire:model="form.existencia_minima" id="existencia_minima" type="text" inputmode="decimal" class="font-mono" placeholder="Avisar cuando baje de…" />
                                <x-input-error :messages="$errors->get('form.existencia_minima')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="existencia_maxima" value="Existencia máxima" />
                                <x-text-input wire:model="form.existencia_maxima" id="existencia_maxima" type="text" inputmode="decimal" class="font-mono" placeholder="No pedir más de…" />
                                <x-input-error :messages="$errors->get('form.existencia_maxima')" class="mt-1.5" />
                            </div>
                        </div>
                    @endif
                </x-card>
            </div>

            <div class="space-y-6 xl:sticky xl:top-8">
                <x-card titulo="Estado">
                    <x-checkbox wire:model="form.activo" label="Artículo activo" ayuda="Si lo desactivas ya no aparece al vender, pero se conserva su historial." />
                </x-card>

                <x-card titulo="Imagen" descripcion="Opcional. Ayuda a reconocer el artículo.">
                    <div class="flex items-center gap-4">
                        <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-lg border border-dashed border-anil-300 bg-papel">
                            @if ($this->imagenVista())
                                <img src="{{ $this->imagenVista() }}" alt="Imagen del artículo" class="size-full object-cover">
                            @else
                                <x-phosphor-image-bold class="size-7 text-anil-300" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <input wire:model="imagen" id="imagen" type="file" accept="image/png,image/jpeg,image/webp"
                                   class="block w-full cursor-pointer text-sm text-anil-800 file:mr-3 file:rounded-lg file:border-0 file:bg-anil-100 file:px-3 file:py-2.5 file:text-sm file:font-semibold file:text-anil-800 hover:file:bg-anil-200">
                            <p class="text-xs text-anil-600" wire:loading wire:target="imagen">Subiendo imagen…</p>
                            @if ($imagenActual && ! $imagen)
                                <x-checkbox wire:model.live="quitarImagen" label="Quitar imagen" class="!min-h-9" />
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-anil-600">PNG, JPG o WebP de hasta 2 MB.</p>
                    <x-input-error :messages="$errors->get('imagen')" class="mt-2" />
                </x-card>
            </div>
        </div>

        {{-- Barra de acciones: fija sobre la barra inferior en teléfono --}}
        <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 flex items-center gap-3 border-t border-anil-100 bg-white/95 px-4 py-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:p-0">
            <x-primary-button wire:loading.attr="disabled" wire:target="guardar,imagen">
                {{ $articulo ? 'Guardar cambios' : 'Crear artículo' }}
            </x-primary-button>
            <x-action-message on="articulo-guardado" />

            @if ($articulo)
                <button type="button" class="ml-auto inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold text-peligro hover:bg-red-50"
                        wire:click="eliminar" wire:confirm="¿Eliminar este artículo? Dejará de aparecer, pero su clave queda reservada y sus ventas pasadas no cambian.">
                    <x-phosphor-trash-bold class="size-4" /> Eliminar
                </button>
            @endif
        </div>
    </form>

    @if ($articulo)
        <x-card titulo="Historial de costo y precios" descripcion="Cada cambio queda registrado con la fecha y quién lo hizo." class="mt-6">
            @forelse ($this->historial as $cambio)
                @if ($loop->first)
                    <div class="-mx-5 overflow-x-auto sm:-mx-6">
                        <table class="w-full min-w-[32rem] text-left text-sm">
                            <thead>
                                <tr class="rotulo border-b border-anil-100">
                                    <th class="px-5 py-2 font-medium sm:px-6">Fecha</th>
                                    <th class="px-3 py-2 font-medium">Campo</th>
                                    <th class="px-3 py-2 text-right font-medium">Antes</th>
                                    <th class="px-3 py-2 text-right font-medium">Ahora</th>
                                    <th class="px-5 py-2 font-medium sm:px-6">Quién</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-anil-50">
                @endif
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-2.5 font-mono text-xs text-anil-700 sm:px-6">{{ $cambio->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2.5 font-medium">{{ $cambio->etiquetaCampo() }}</td>
                                    <td class="px-3 py-2.5 text-right font-mono text-anil-600">{{ $cambio->valor_anterior === null ? '—' : '$'.number_format((float) $cambio->valor_anterior, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right font-mono font-semibold">{{ $cambio->valor_nuevo === null ? '—' : '$'.number_format((float) $cambio->valor_nuevo, 2) }}</td>
                                    <td class="px-5 py-2.5 text-anil-700 sm:px-6">{{ $cambio->usuario?->name ?? ($cambio->origen === 'importacion' ? 'Importación' : 'Sistema') }}</td>
                                </tr>
                @if ($loop->last)
                            </tbody>
                        </table>
                    </div>
                @endif
            @empty
                <p class="text-sm text-anil-700">Todavía no hay cambios de costo o precio registrados.</p>
            @endforelse
        </x-card>
    @endif
</div>
