<?php

use App\Models\Cliente;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $filtro = '';

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $telefono = '';

    public string $correo = '';

    public string $rfc = '';

    public string $direccion = '';

    public string $lista_precios = '1';

    public string $limite_credito = '0';

    public string $notas = '';

    public bool $activo = true;

    public function mount(): void
    {
        $this->authorize('clientes.ver');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function puedeAdministrar(): bool
    {
        return auth()->user()->can('clientes.administrar');
    }

    #[Computed]
    public function puedeCredito(): bool
    {
        return auth()->user()->can('clientes.credito');
    }

    #[Computed]
    public function resumen(): array
    {
        return [
            'clientes' => Cliente::query()->activos()->count(),
            'deudores' => Cliente::query()->conSaldo()->count(),
            'por_cobrar' => (string) Cliente::query()->sum('saldo'),
        ];
    }

    #[Computed]
    public function clientes()
    {
        $patron = '%'.addcslashes($this->buscar, '%_\\').'%';

        return Cliente::query()
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', $patron)->orWhere('telefono', 'like', $patron)))
            ->when($this->filtro === 'saldo', fn ($q) => $q->conSaldo())
            ->when($this->filtro === 'credito', fn ($q) => $q->where('limite_credito', '>', 0))
            ->when($this->filtro === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when(! in_array($this->filtro, ['inactivos'], true), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')->paginate(20);
    }

    public function nuevo(): void
    {
        $this->authorize('clientes.administrar');
        $this->reset('editandoId', 'nombre', 'telefono', 'correo', 'rfc', 'direccion', 'notas');
        $this->fill(['lista_precios' => '1', 'limite_credito' => '0', 'activo' => true]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'cliente');
    }

    public function editar(int $id): void
    {
        $this->authorize('clientes.administrar');
        $c = Cliente::query()->findOrFail($id);
        $this->fill([
            'editandoId' => $c->id, 'nombre' => $c->nombre, 'telefono' => $c->telefono ?? '', 'correo' => $c->correo ?? '',
            'rfc' => $c->rfc ?? '', 'direccion' => $c->direccion ?? '', 'lista_precios' => (string) $c->lista_precios,
            'limite_credito' => rtrim(rtrim((string) $c->limite_credito, '0'), '.') ?: '0', 'notas' => $c->notas ?? '', 'activo' => $c->activo,
        ]);
        $this->resetValidation();
        $this->dispatch('abrir-modal', nombre: 'cliente');
    }

    public function guardar(): void
    {
        $this->authorize('clientes.administrar');

        $d = $this->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:120'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['required', Rule::in(['1', '2', '3'])],
            'limite_credito' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ], ['rfc.regex' => 'El RFC no tiene un formato válido.'], ['rfc' => 'RFC', 'correo' => 'correo', 'lista_precios' => 'lista de precios', 'limite_credito' => 'límite de crédito']);

        $nulo = fn (?string $v) => ($v = trim((string) $v)) === '' ? null : $v;

        $datos = [
            'nombre' => trim($d['nombre']),
            'telefono' => $nulo($d['telefono']),
            'correo' => $nulo($d['correo']),
            'rfc' => $nulo($d['rfc']) ? mb_strtoupper($nulo($d['rfc'])) : null,
            'direccion' => $nulo($d['direccion']),
            'notas' => $nulo($d['notas']),
            'activo' => $d['activo'],
        ];

        // Lista de precios y límite de crédito son decisiones del dueño: requieren su propio permiso.
        if ($this->puedeCredito) {
            $datos['lista_precios'] = (int) $d['lista_precios'];
            $datos['limite_credito'] = $d['limite_credito'];
        }

        Cliente::query()->updateOrCreate(['id' => $this->editandoId], $datos);

        unset($this->clientes, $this->resumen);
        $this->dispatch('cerrar-modal', nombre: 'cliente');
    }
}; ?>

<div>
    <x-slot name="titulo">Clientes</x-slot>

    @if ($this->puedeAdministrar)
        <div class="mb-6 flex flex-wrap items-center justify-end gap-3">
            <x-primary-button type="button" wire:click="nuevo"><x-phosphor-plus-bold class="size-4" /> Nuevo cliente</x-primary-button>
        </div>
    @endif

    @php $r = $this->resumen; @endphp

    <div class="mb-6 grid grid-cols-3 gap-3">
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]"><p class="rotulo">Clientes</p><p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['clientes']) }}</p></div>
        <div class="rounded-xl border border-anil-100 bg-white p-4 shadow-[0_2px_0_0_var(--color-anil-100)]"><p class="rotulo">Con deuda</p><p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ number_format($r['deudores']) }}</p></div>
        <div @class(['rounded-xl border p-4 shadow-[0_2px_0_0_var(--color-anil-100)]', 'border-etiqueta bg-etiqueta/20' => (float) $r['por_cobrar'] > 0, 'border-anil-100 bg-white' => (float) $r['por_cobrar'] <= 0])><p class="rotulo">Por cobrar</p><p class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">${{ number_format((float) $r['por_cobrar'], 2) }}</p></div>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
        <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="Buscar por nombre o teléfono" aria-label="Buscar cliente" autocomplete="off"
               class="block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
        <x-select wire:model.live="filtro" aria-label="Mostrar">
            <option value="">Activos</option>
            <option value="saldo">Con deuda</option>
            <option value="credito">Con crédito autorizado</option>
            <option value="inactivos">Inactivos</option>
        </x-select>
    </div>

    @php $lista = $this->clientes; @endphp

    @if ($lista->isEmpty())
        <x-empty icono="users" titulo="No hay clientes" :texto="$buscar !== '' || $filtro !== '' ? 'Ningún cliente cumple este filtro.' : 'Da de alta a tus clientes frecuentes para darles crédito o precios de mayoreo.'" />
    @else
        <div class="overflow-hidden rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]">
            <ul class="divide-y divide-anil-50">
                @foreach ($lista as $c)
                    <li wire:key="cl-{{ $c->id }}" class="flex items-center gap-2 pr-2 sm:pr-4">
                        <a href="{{ route('clientes.cuenta', $c) }}" wire:navigate class="grid min-h-16 min-w-0 flex-1 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 px-4 py-3 hover:bg-anil-50/60 sm:px-5">
                            <span class="min-w-0">
                                <span @class(['block truncate font-semibold', 'text-anil-400 line-through' => ! $c->activo])>{{ $c->nombre }}</span>
                                <span class="text-xs text-anil-600">{{ $c->telefono ?: 'Sin teléfono' }} · Precio {{ $c->lista_precios }}{{ $c->tieneCredito() ? ' · Límite $'.number_format((float) $c->limite_credito, 2) : '' }}</span>
                            </span>
                            <span class="text-right">
                                @if ((float) $c->saldo > 0)
                                    <span class="block font-display text-lg font-extrabold text-red-700">${{ number_format((float) $c->saldo, 2) }}</span>
                                    <span class="text-xs text-anil-600">debe</span>
                                @else
                                    <x-badge :tono="$c->tieneCredito() ? 'ok' : 'gris'">{{ $c->tieneCredito() ? 'Al corriente' : 'Sin crédito' }}</x-badge>
                                @endif
                            </span>
                        </a>
                        @if ($this->puedeAdministrar)
                            <button type="button" wire:click="editar({{ $c->id }})" class="grid size-11 shrink-0 place-items-center rounded-lg text-anil-700 hover:bg-anil-50" aria-label="Editar {{ $c->nombre }}"><x-phosphor-pencil-simple-bold class="size-5" /></button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-5">{{ $lista->links() }}</div>
    @endif

    @if ($this->puedeAdministrar)
        <x-modal nombre="cliente" :titulo="$editandoId ? 'Editar cliente' : 'Nuevo cliente'" max-width="lg">
            <form wire:submit="guardar" class="space-y-5">
                <div>
                    <x-input-label for="cl_nombre" value="Nombre" />
                    <x-text-input wire:model="nombre" id="cl_nombre" type="text" maxlength="120" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1.5" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="cl_tel" value="Teléfono" />
                        <x-text-input wire:model="telefono" id="cl_tel" type="tel" maxlength="20" inputmode="tel" autocomplete="off" />
                        <x-input-error :messages="$errors->get('telefono')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="cl_correo" value="Correo" />
                        <x-text-input wire:model="correo" id="cl_correo" type="email" maxlength="120" autocomplete="off" />
                        <x-input-error :messages="$errors->get('correo')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="cl_rfc" value="RFC" />
                        <x-text-input wire:model="rfc" id="cl_rfc" type="text" maxlength="13" class="font-mono uppercase" autocomplete="off" />
                        <x-input-error :messages="$errors->get('rfc')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="cl_dir" value="Dirección" />
                        <x-text-input wire:model="direccion" id="cl_dir" type="text" maxlength="255" autocomplete="off" />
                    </div>
                </div>

                @if ($this->puedeCredito)
                    <div class="grid gap-5 rounded-xl bg-anil-50 p-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="cl_lista" value="Lista de precios" />
                            <x-select wire:model="lista_precios" id="cl_lista">
                                <option value="1">Precio 1 (público)</option>
                                <option value="2">Precio 2 (mayoreo)</option>
                                <option value="3">Precio 3 (mayoreo)</option>
                            </x-select>
                            <x-input-error :messages="$errors->get('lista_precios')" class="mt-1.5" />
                        </div>
                        <div>
                            <x-input-label for="cl_limite" value="Límite de crédito" />
                            <x-text-input wire:model="limite_credito" id="cl_limite" type="text" inputmode="decimal" class="font-mono" autocomplete="off" />
                            <p class="mt-1 text-xs text-anil-600">0 = no se le fía.</p>
                            <x-input-error :messages="$errors->get('limite_credito')" class="mt-1.5" />
                        </div>
                    </div>
                @endif

                <div>
                    <x-input-label for="cl_notas" value="Notas" />
                    <x-text-input wire:model="notas" id="cl_notas" type="text" maxlength="2000" autocomplete="off" />
                </div>
                <x-checkbox wire:model="activo" label="Cliente activo" />
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('cerrar-modal')">Cancelar</x-secondary-button>
                    <x-primary-button>Guardar</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
