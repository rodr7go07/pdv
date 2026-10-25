<?php

use App\Actions\Inventario\GestionarConteo;
use App\Models\Articulo;
use App\Models\Conteo;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Conteo $conteo;

    public ?int $articuloId = null;

    public string $cantidad = '';

    public ?string $aviso = null;

    public function mount(Conteo $conteo): void
    {
        $this->authorize('inventario.conteo');
        $this->conteo = $conteo;
    }

    #[On('articulo-elegido')]
    public function elegirArticulo(int $id): void
    {
        if (! $this->conteo->enProceso()) {
            return;
        }

        $this->articuloId = $id;
        $this->aviso = null;
        $this->resetValidation();

        // Si ya se contó, se muestra la cantidad anterior para corregirla.
        $previa = $this->conteo->partidas()->where('articulo_id', $id)->value('cantidad_contada');
        $this->cantidad = $previa !== null ? (rtrim(rtrim((string) $previa, '0'), '.') ?: '0') : '';
    }

    #[Computed]
    public function articulo(): ?Articulo
    {
        return $this->articuloId ? Articulo::query()->with('unidad')->find($this->articuloId) : null;
    }

    public function guardarCantidad(GestionarConteo $gestor): void
    {
        $this->authorize('inventario.conteo');
        abort_unless($this->articulo, 422);
        $this->resetValidation();

        try {
            $partida = $gestor->capturar($this->conteo, $this->articulo, trim($this->cantidad));
        } catch (ValidationException $e) {
            $this->addError('cantidad', collect($e->errors())->flatten()->first());

            return;
        }

        $this->aviso = 'Guardado: '.$this->articulo->descripcion.' = '.(rtrim(rtrim((string) $partida->cantidad_contada, '0'), '.') ?: '0');
        $this->reset('articuloId', 'cantidad');
        unset($this->articulo, $this->partidas);
        $this->dispatch('enfocar-selector');
    }

    public function omitir(): void
    {
        $this->reset('articuloId', 'cantidad');
        $this->dispatch('enfocar-selector');
    }

    public function quitar(GestionarConteo $gestor, int $id): void
    {
        $this->authorize('inventario.conteo');
        $gestor->quitar($this->conteo, $id);
        unset($this->partidas);
    }

    public function aplicar(GestionarConteo $gestor): void
    {
        $this->authorize('inventario.conteo');
        $resultado = $gestor->aplicar($this->conteo, auth()->user());

        $this->conteo->refresh();
        $this->aviso = "Conteo aplicado: se ajustaron {$resultado['ajustados']} artículos y {$resultado['sin_diferencia']} estaban correctos.";
        unset($this->partidas);
    }

    public function cancelar(GestionarConteo $gestor): void
    {
        $this->authorize('inventario.conteo');
        $gestor->cancelar($this->conteo);
        $this->conteo->refresh();
    }

    #[Computed]
    public function partidas()
    {
        return $this->conteo->partidas()->with(['articulo.unidad'])->latest('updated_at')->latest('id')->get();
    }
}; ?>

<div>
    <x-slot name="titulo">{{ $conteo->nombre }}</x-slot>
    <x-slot name="acciones">
        <a href="{{ route('inventario.conteos') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-anil-700 hover:bg-white">
            <x-phosphor-arrow-left-bold class="size-4" /> Todos los conteos
        </a>
    </x-slot>

    @if ($aviso)
        <p class="mb-5 flex items-start gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200" role="status">
            <x-phosphor-check-circle-bold class="mt-0.5 size-5 shrink-0" /> {{ $aviso }}
        </p>
    @endif

    @if (! $conteo->enProceso())
        <p class="mb-5 rounded-lg bg-anil-50 px-4 py-3 text-sm font-medium text-anil-800">
            Este conteo está {{ $conteo->estado === 'aplicado' ? 'aplicado' : 'cancelado' }}{{ $conteo->aplicado_at ? ' desde el '.$conteo->aplicado_at->format('d/m/Y H:i') : '' }}. Ya no se puede modificar.
        </p>
    @else
        <x-card class="mb-6">
            @if (! $this->articulo)
                <livewire:articulos.selector origen="conteo" :solo-inventariables="true" placeholder="Escanea o busca" />
            @else
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-lg font-extrabold">{{ $this->articulo->descripcion }}</p>
                        <p class="font-mono text-xs text-anil-700">{{ $this->articulo->clave }}</p>
                    </div>
                    <button type="button" wire:click="omitir" class="shrink-0 text-sm font-semibold text-rosa-600 underline">Cambiar</button>
                </div>

                <form wire:submit="guardarCantidad" class="mt-4 flex items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <x-input-label for="cantidad" value="¿Cuántos hay? ({{ $this->articulo->unidad->abreviatura }})" />
                        <x-text-input wire:model="cantidad" id="cantidad" type="text" inputmode="decimal" class="font-mono text-2xl" required autofocus autocomplete="off"
                                      x-init="$nextTick(() => $el.select())" />
                    </div>
                    <x-primary-button class="shrink-0">Guardar</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('cantidad')" class="mt-2" />
            @endif
        </x-card>
    @endif

    @php $partidas = $this->partidas; @endphp

    <x-card titulo="Lo contado" :descripcion="$partidas->count().' '.($partidas->count() === 1 ? 'artículo' : 'artículos').'. La diferencia es lo contado menos lo que decía el sistema.'" :padding="false">
        @if ($partidas->isEmpty())
            <div class="p-5"><x-empty icono="barcode" titulo="Todavía no has contado nada" texto="Escanea un artículo, escribe cuántos hay y guarda." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead>
                        <tr class="rotulo border-b border-anil-100 bg-anil-50/60">
                            <th class="px-5 py-3 font-medium">Artículo</th>
                            <th class="px-3 py-3 text-right font-medium">Sistema</th>
                            <th class="px-3 py-3 text-right font-medium">Contado</th>
                            <th class="px-3 py-3 text-right font-medium">Diferencia</th>
                            @if ($conteo->enProceso())<th class="w-12 px-3 py-3"><span class="sr-only">Quitar</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-anil-50">
                        @foreach ($partidas as $p)
                            @php $d = (float) $p->diferencia(); @endphp
                            <tr wire:key="cp-{{ $p->id }}">
                                <td class="px-5 py-2.5">
                                    <span class="block font-semibold">{{ $p->articulo->descripcion }}</span>
                                    <span class="font-mono text-xs text-anil-600">{{ $p->articulo->clave }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-anil-700">{{ rtrim(rtrim((string) $p->existencia_sistema, '0'), '.') ?: '0' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono font-semibold">{{ rtrim(rtrim((string) $p->cantidad_contada, '0'), '.') ?: '0' }}</td>
                                <td @class(['whitespace-nowrap px-3 py-2.5 text-right font-mono font-bold', 'text-emerald-700' => $d > 0, 'text-red-700' => $d < 0, 'text-anil-400' => $d == 0])>
                                    {{ $d > 0 ? '+' : '' }}{{ rtrim(rtrim($p->diferencia(), '0'), '.') ?: '0' }}
                                </td>
                                @if ($conteo->enProceso())
                                    <td class="px-3 py-2.5">
                                        <button type="button" wire:click="quitar({{ $p->id }})" class="grid size-10 place-items-center rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar {{ $p->articulo->descripcion }}">
                                            <x-phosphor-x-bold class="size-4" />
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($conteo->enProceso() && $partidas->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-primary-button type="button" wire:click="aplicar" wire:loading.attr="disabled"
                              wire:confirm="¿Aplicar el conteo? El inventario se corregirá con las diferencias y no se puede deshacer.">
                Aplicar al inventario
            </x-primary-button>
            <button type="button" wire:click="cancelar" wire:confirm="¿Cancelar este conteo? No se cambiará el inventario."
                    class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-peligro hover:bg-red-50">Cancelar conteo</button>
        </div>
    @endif
</div>
