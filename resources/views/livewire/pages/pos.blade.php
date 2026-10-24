<?php

use App\Actions\Articulos\GuardarArticulo;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\TipoImpuesto;
use App\Http\Controllers\PosController;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Departamento;
use App\Models\Turno;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use App\Models\VentaEnEspera;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

new #[Layout('layouts.pos')] class extends Component
{
    #[Locked]
    public ?int $cajaId = null;

    public function mount(): void
    {
        $this->authorize('ventas.cobrar');

        // La caja la define el turno abierto del usuario.
        $this->cajaId = $this->turno?->caja_id;
    }

    #[Computed]
    public function turno(): ?Turno
    {
        return Turno::abiertoDe(auth()->user());
    }

    #[Computed]
    public function caja(): ?Caja
    {
        return $this->turno?->caja;
    }

    #[Computed]
    public function puedeAlta(): bool
    {
        $u = auth()->user();

        return $u->can('articulos.crear') && $u->can('articulos.editar_precio');
    }

    #[Computed]
    public function departamentos()
    {
        return Departamento::query()->activos()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function unidades()
    {
        return UnidadMedida::query()->activas()->orderBy('nombre')->get(['id', 'nombre', 'abreviatura']);
    }

    #[Computed]
    public function ivas()
    {
        return Impuesto::query()->activos()->where('tipo', TipoImpuesto::Iva)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** @return array<string, mixed> */
    public function configuracionJs(): array
    {
        return [
            'cajaId' => $this->cajaId,
            'lista' => 1,
            'urlBuscar' => route('pos.buscar'),
            'urlPorIds' => route('pos.por-ids'),
            'urlTicket' => route('ventas.ticket', ['venta' => '__ID__']),
            'puedeDescuento' => auth()->user()->can('ventas.descuento'),
            'puedeCliente' => auth()->user()->can('clientes.ver'),
            'puedeAlta' => $this->puedeAlta,
            'autoImprimir' => (bool) $this->caja?->imprimir_automatico,
            'venderSinExistencia' => (bool) Configuracion::actual()->vender_sin_existencia,
            'iva16' => (string) ($this->ivas->firstWhere('nombre', 'IVA 16%')?->id ?? ''),
            'depto' => (string) ($this->departamentos->first()?->id ?? ''),
            'unidad' => (string) ($this->unidades->firstWhere('abreviatura', 'pza')?->id ?? $this->unidades->first()?->id ?? ''),
        ];
    }

    /** Totales exactos del servidor para mostrar en la pantalla de cobro. */
    public function previsualizar(array $lineas, array $descuento, int $lista, ?int $clienteId = null): array
    {
        try {
            $cliente = $this->cliente($clienteId);
            $r = app(RegistrarVenta::class)->calcular(auth()->user(), $this->limpiarLineas($lineas), $this->limpiarDescuento($descuento), $lista, $cliente);
        } catch (ValidationException|HttpException $e) {
            return ['ok' => false, 'mensaje' => $this->mensaje($e)];
        }

        return [
            'ok' => true, 'total' => $r['total'], 'descuento' => $r['descuento'], 'subtotal' => $r['subtotal'], 'impuestos' => $r['impuestos'],
            'disponible' => $cliente?->tieneCredito() ? $cliente->disponible() : '0.00',
        ];
    }

    public function cobrar(array $lineas, array $pagos, array $descuento, int $lista, ?int $clienteId = null): array
    {
        $caja = $this->caja;

        if (! $caja) {
            return ['ok' => false, 'mensaje' => 'Abre tu turno de caja antes de vender.'];
        }

        try {
            $venta = app(RegistrarVenta::class)(
                $caja,
                auth()->user(),
                $this->limpiarLineas($lineas),
                $this->limpiarPagos($pagos),
                $this->limpiarDescuento($descuento),
                $lista,
                $this->cliente($clienteId),
            );
        } catch (ValidationException|HttpException $e) {
            return ['ok' => false, 'mensaje' => $this->mensaje($e)];
        }

        return [
            'ok' => true,
            'id' => $venta->id,
            'folio' => $venta->folio,
            'total' => $venta->total,
            'recibido' => $venta->efectivo_recibido,
            'cambio' => $venta->cambio,
        ];
    }

    // ─── Clientes ───────────────────────────────────────────────────────────────
    /** Búsqueda de clientes para la venta (por nombre o teléfono). */
    public function buscarClientes(string $texto): array
    {
        if (! auth()->user()->can('clientes.ver')) {
            return [];
        }

        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return [];
        }

        $patron = '%'.addcslashes($texto, '%_\\').'%';

        return Cliente::query()->activos()
            ->where(fn ($q) => $q->where('nombre', 'like', $patron)->orWhere('telefono', 'like', $patron))
            ->orderBy('nombre')->limit(10)->get()
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'telefono' => $c->telefono,
                'lista' => $c->lista_precios,
                'credito' => $c->tieneCredito(),
                'saldo' => $c->saldo,
                'disponible' => $c->tieneCredito() ? $c->disponible() : '0.00',
            ])->all();
    }

    /** @throws ValidationException */
    private function cliente(?int $id): ?Cliente
    {
        if (! $id) {
            return null;
        }

        abort_unless(auth()->user()->can('clientes.ver'), 403, 'No tienes permiso para vender a clientes.');

        $cliente = Cliente::query()->activos()->find($id);

        if (! $cliente) {
            throw ValidationException::withMessages(['cliente' => 'Ese cliente ya no está disponible.']);
        }

        return $cliente;
    }

    // ─── Ventas en espera ───────────────────────────────────────────────────────
    public function guardarEspera(array $lineas, array $descGlobal, ?string $nombre): array
    {
        $this->authorize('ventas.cobrar');

        $limpias = collect($lineas)->take(300)->map(fn ($l) => [
            'id' => (int) ($l['id'] ?? 0),
            'clave' => (string) ($l['clave'] ?? ''),
            'descripcion' => (string) ($l['descripcion'] ?? ''),
            'unidad' => (string) ($l['unidad'] ?? ''),
            'decimales' => (bool) ($l['decimales'] ?? false),
            'granel' => (bool) ($l['granel'] ?? false),
            'inventariable' => (bool) ($l['inventariable'] ?? true),
            'existencia' => (string) ($l['existencia'] ?? '0'),
            'precios' => array_slice(array_map(fn ($p) => $p === null ? null : (string) $p, (array) ($l['precios'] ?? [])), 0, 3),
            'cantidad' => (string) ($l['cantidad'] ?? '1'),
            'descTipo' => in_array($l['descTipo'] ?? '', ['pct', 'monto'], true) ? $l['descTipo'] : '',
            'descValor' => is_numeric($l['descValor'] ?? null) ? (string) $l['descValor'] : '',
        ])->filter(fn ($l) => $l['id'] > 0 && is_numeric($l['cantidad']) && (float) $l['cantidad'] > 0)->values()->all();

        if ($limpias === []) {
            return ['ok' => false, 'mensaje' => 'No hay nada que guardar.'];
        }

        if (VentaEnEspera::query()->where('caja_id', $this->cajaId)->count() >= 30) {
            return ['ok' => false, 'mensaje' => 'Ya hay demasiadas ventas en espera. Termina o descarta alguna.'];
        }

        VentaEnEspera::query()->create([
            'caja_id' => $this->cajaId,
            'user_id' => auth()->id(),
            'nombre' => $nombre ? mb_substr(trim($nombre), 0, 60) : null,
            'carrito' => ['lineas' => $limpias, 'descGlobal' => $this->limpiarDescuentoUi($descGlobal)],
            'total' => 0,
        ]);

        return ['ok' => true];
    }

    public function listarEsperas(): array
    {
        $this->authorize('ventas.cobrar');

        return VentaEnEspera::query()->with('usuario:id,name')->where('caja_id', $this->cajaId)->latest()->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'piezas' => count($e->carrito['lineas'] ?? []),
                'usuario' => $e->usuario?->name,
                'hora' => $e->created_at->format('H:i'),
            ])->all();
    }

    public function recuperarEspera(int $id): array
    {
        $this->authorize('ventas.cobrar');

        $espera = VentaEnEspera::query()->where('caja_id', $this->cajaId)->find($id);

        if (! $espera) {
            return ['ok' => false, 'mensaje' => 'Esa venta ya no está en espera.'];
        }

        $datos = $espera->carrito;
        $espera->delete();

        return ['ok' => true, 'lineas' => $datos['lineas'] ?? [], 'descGlobal' => $datos['descGlobal'] ?? ['tipo' => '', 'valor' => '']];
    }

    public function descartarEspera(int $id): void
    {
        $this->authorize('ventas.cobrar');

        VentaEnEspera::query()->where('caja_id', $this->cajaId)->whereKey($id)->delete();
    }

    // ─── Alta rápida de artículo ────────────────────────────────────────────────
    public function altaRapida(array $datos): array
    {
        if (! $this->puedeAlta) {
            return ['ok' => false, 'mensaje' => 'No tienes permiso para dar de alta artículos.'];
        }

        try {
            $articulo = app(GuardarArticulo::class)(null, [
                'clave' => $datos['clave'] ?? '',
                'descripcion' => $datos['descripcion'] ?? '',
                'departamento_id' => $datos['departamento_id'] ?? null,
                'unidad_medida_id' => $datos['unidad_medida_id'] ?? null,
                'costo' => '0',
                'precio_1' => $datos['precio'] ?? '',
                'impuestos' => array_filter([$datos['iva_id'] ?? null]),
                'inventariable' => true,
                'granel' => false,
                'activo' => true,
            ], auth()->user(), true);
        } catch (ValidationException $e) {
            return ['ok' => false, 'mensaje' => $this->mensaje($e)];
        }

        return ['ok' => true, 'articulo' => PosController::articuloJson($articulo)];
    }

    // ─── Ayudas ─────────────────────────────────────────────────────────────────
    /** @return list<array<string, mixed>> */
    private function limpiarLineas(array $lineas): array
    {
        return collect($lineas)->take(300)->map(fn ($l) => [
            'articulo_id' => $l['articulo_id'] ?? null,
            'cantidad' => (string) ($l['cantidad'] ?? ''),
            'descuento_tipo' => $l['descuento_tipo'] ?? null,
            'descuento_valor' => $l['descuento_valor'] ?? null,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function limpiarPagos(array $pagos): array
    {
        return collect($pagos)->take(6)->map(fn ($p) => [
            'forma' => (string) ($p['forma'] ?? ''),
            'monto' => (string) ($p['monto'] ?? ''),
            'referencia' => isset($p['referencia']) ? mb_substr((string) $p['referencia'], 0, 60) : null,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function limpiarDescuento(array $d): array
    {
        return in_array($d['tipo'] ?? null, ['pct', 'monto'], true) ? ['tipo' => $d['tipo'], 'valor' => (string) ($d['valor'] ?? '')] : [];
    }

    /** @return array{tipo: string, valor: string} */
    private function limpiarDescuentoUi(array $d): array
    {
        return in_array($d['tipo'] ?? '', ['pct', 'monto'], true) && is_numeric($d['valor'] ?? null)
            ? ['tipo' => $d['tipo'], 'valor' => (string) $d['valor']]
            : ['tipo' => '', 'valor' => ''];
    }

    private function mensaje(ValidationException|HttpException $e): string
    {
        return $e instanceof ValidationException
            ? (string) collect($e->errors())->flatten()->first()
            : ($e->getMessage() ?: 'No tienes permiso para esta acción.');
    }
}; ?>

<div x-data="pos(@js($this->configuracionJs()))" x-on:keydown.window="teclado($event)" class="flex h-dvh flex-col bg-papel">
    @if (! $this->caja)
        <div class="grid flex-1 place-items-center p-6">
            <x-empty icono="cash-register" titulo="Abre tu turno para empezar a vender" texto="Antes de cobrar necesitas abrir tu turno de caja con el fondo inicial.">
                <div class="flex flex-wrap justify-center gap-3">
                    @can('caja.abrir')
                        <a href="{{ route('caja') }}" wire:navigate class="rounded-lg bg-rosa-500 px-4 py-2.5 text-sm font-extrabold uppercase tracking-wide text-white shadow-[0_3px_0_0_var(--color-rosa-700)]">Abrir mi turno</a>
                    @endcan
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-lg border border-anil-200 px-4 py-2.5 text-sm font-semibold text-anil-800">Volver al inicio</a>
                </div>
            </x-empty>
        </div>
    @else
        {{-- Barra superior --}}
        <header class="flex h-14 shrink-0 items-center gap-2 bg-anil-900 px-2 text-white sm:gap-3 sm:px-4">
            <a href="{{ route('dashboard') }}" wire:navigate class="grid size-11 shrink-0 place-items-center rounded-lg text-anil-200 hover:bg-anil-800" aria-label="Salir de la caja" title="Salir de la caja">
                <x-phosphor-arrow-left-bold class="size-5" />
            </a>
            <x-application-logo class="hidden size-7 shrink-0 text-rosa-500 sm:block" />
            <div class="min-w-0 leading-tight">
                <p class="truncate font-display text-sm font-extrabold [font-stretch:115%]">{{ \App\Models\Configuracion::actual()->nombre_tienda }}</p>
                <p class="rotulo !text-anil-300">{{ $this->caja->nombre }}</p>
            </div>
            <div class="ml-auto flex items-center gap-1">
                <span class="mr-2 hidden text-sm text-anil-200 md:block">{{ auth()->user()->name }}</span>
                <button type="button" x-on:click="modal = 'atajos'" class="grid size-11 place-items-center rounded-lg text-anil-200 hover:bg-anil-800" aria-label="Atajos de teclado" title="Atajos de teclado">
                    <x-phosphor-keyboard-bold class="size-5" />
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            {{-- Captura y carrito --}}
            <section class="flex min-h-0 flex-1 flex-col">
                <div class="shrink-0 border-b border-anil-100 bg-white p-3 sm:p-4">
                    <div class="relative">
                        <x-phosphor-barcode-bold class="pointer-events-none absolute left-3.5 top-1/2 size-6 -translate-y-1/2 text-anil-400" />
                        <input x-ref="entrada" x-model="entrada" x-on:keydown.enter.prevent="enviarEntrada()" type="text" inputmode="text" autofocus
                               autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Código del artículo"
                               placeholder="Escanea o escribe el código  ·  5* para varias piezas"
                               class="block w-full rounded-xl border-2 border-anil-200 bg-white py-3.5 pl-12 pr-4 font-mono text-lg text-tinta placeholder:font-sans placeholder:text-base placeholder:text-anil-300 focus:border-rosa-500 focus:ring-4 focus:ring-rosa-500/15 focus:outline-none">
                    </div>
                    <div class="mt-2 flex min-h-6 flex-wrap items-center gap-2 text-sm" aria-live="polite">
                        <template x-if="multiplicador">
                            <span class="inline-flex items-center gap-1 rounded-md bg-rosa-50 px-2 py-0.5 font-mono text-sm font-bold text-rosa-700 ring-1 ring-rosa-100">
                                ×<span x-text="multiplicador"></span>
                            </span>
                        </template>
                        <span x-show="aviso" x-text="aviso" x-cloak
                              :class="{ 'text-red-700 font-semibold': avisoTipo === 'error', 'text-emerald-700 font-semibold': avisoTipo === 'ok', 'text-anil-700': avisoTipo === 'info' }"></span>
                    </div>
                </div>

                <div x-ref="lista" class="min-h-0 flex-1 overflow-y-auto">
                    <div x-show="!lineas.length" class="grid h-full place-items-center p-6 text-center" x-cloak>
                        <div>
                            <x-phosphor-barcode-bold class="mx-auto size-12 text-anil-200" />
                            <p class="mt-3 font-display text-lg font-extrabold text-anil-900">Escanea el primer artículo</p>
                            <p class="mt-1 text-sm text-anil-700">También puedes buscarlo por nombre con <kbd class="rounded bg-anil-100 px-1.5 py-0.5 font-mono text-xs">F2</kbd>.</p>
                        </div>
                    </div>

                    <ul class="divide-y divide-anil-50" x-show="lineas.length" x-cloak>
                        <template x-for="(l, i) in lineas" :key="l.uid">
                            <li :data-sel="sel === i" x-on:click="sel = i"
                                :class="sel === i ? 'bg-rosa-50/70' : 'bg-white'"
                                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 px-3 py-3 sm:grid-cols-[minmax(0,1fr)_9.5rem_6rem_6.5rem_5rem] sm:px-4">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold leading-snug" x-text="l.descripcion"></p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-anil-600">
                                        <span class="font-mono" x-text="l.clave"></span>
                                        <span x-show="l.descTipo" class="rounded bg-etiqueta/40 px-1.5 py-0.5 font-semibold text-amber-900"
                                              x-text="'Desc. ' + (l.descTipo === 'pct' ? l.descValor + '%' : dinero(Math.round(l.descValor * 100)))"></span>
                                        <span x-show="cfg.venderSinExistencia === false && l.inventariable && parseFloat(l.existencia) < parseFloat(l.cantidad)" class="font-semibold text-red-700">Sin existencia suficiente</span>
                                    </p>
                                </div>

                                <p class="text-right font-display text-lg font-extrabold sm:order-last sm:col-start-4 sm:text-base" x-text="dinero(totalLinea(l))"></p>

                                <div class="col-span-2 flex items-center justify-between gap-2 sm:col-span-1 sm:col-start-2 sm:row-start-1 sm:justify-center">
                                    <button type="button" x-on:click.stop="cambiarCantidad(i, -1)" class="grid size-11 place-items-center rounded-lg border border-anil-200 bg-white text-anil-800 active:bg-anil-100 sm:size-9" aria-label="Quitar una pieza">
                                        <x-phosphor-minus-bold class="size-4" />
                                    </button>
                                    <input type="text" inputmode="decimal" :value="cantidadTexto(l)" x-on:change="fijarCantidad(i, $event.target.value); $event.target.value = cantidadTexto(l)"
                                           x-on:keydown.enter.stop.prevent="$event.target.blur(); enfocar()" x-on:focus="$event.target.select()"
                                           class="h-11 w-20 rounded-lg border border-anil-200 bg-white px-1 text-center font-mono text-base font-bold focus:border-rosa-500 focus:ring-2 focus:ring-rosa-500/20 sm:h-9 sm:w-[4.75rem]" :aria-label="'Cantidad de ' + l.descripcion">
                                    <button type="button" x-on:click.stop="cambiarCantidad(i, 1)" class="grid size-11 place-items-center rounded-lg border border-anil-200 bg-white text-anil-800 active:bg-anil-100 sm:size-9" aria-label="Agregar una pieza">
                                        <x-phosphor-plus-bold class="size-4" />
                                    </button>
                                    {{-- En teléfono los botones de descuento y quitar van junto a la cantidad --}}
                                    <button type="button" x-on:click.stop="abrirDescuentoLinea(i)" x-show="cfg.puedeDescuento" class="grid size-11 place-items-center rounded-lg border border-anil-200 bg-white text-anil-700 active:bg-anil-100 sm:hidden" aria-label="Descuento a este artículo">
                                        <x-phosphor-percent-bold class="size-4" />
                                    </button>
                                    <button type="button" x-on:click.stop="quitar(i)" class="grid size-11 place-items-center rounded-lg border border-anil-200 bg-white text-anil-700 active:bg-red-50 sm:hidden" aria-label="Quitar artículo">
                                        <x-phosphor-trash-bold class="size-4" />
                                    </button>
                                </div>

                                <p class="hidden text-right font-mono text-sm text-anil-700 sm:col-start-3 sm:row-start-1 sm:block" x-text="dinero(precioCents(l)) + '/' + l.unidad"></p>

                                <div class="hidden justify-end gap-1 sm:col-start-5 sm:row-start-1 sm:flex">
                                    <button type="button" x-on:click.stop="abrirDescuentoLinea(i)" x-show="cfg.puedeDescuento" class="grid size-9 place-items-center rounded-lg text-anil-600 hover:bg-anil-100" aria-label="Descuento a este artículo" title="Descuento">
                                        <x-phosphor-percent-bold class="size-4" />
                                    </button>
                                    <button type="button" x-on:click.stop="quitar(i)" class="grid size-9 place-items-center rounded-lg text-anil-600 hover:bg-red-50 hover:text-red-700" aria-label="Quitar artículo" title="Quitar">
                                        <x-phosphor-trash-bold class="size-4" />
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>

            {{-- Resumen y acciones --}}
            <aside class="shrink-0 border-t border-anil-100 bg-white lg:flex lg:w-[22rem] lg:flex-col lg:border-l lg:border-t-0">
                <div class="grid grid-cols-5 gap-1.5 border-b border-anil-50 p-2 lg:grid-cols-2 lg:gap-2 lg:p-4">
                    <button type="button" x-on:click="abrirBusqueda()" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg border border-anil-200 text-[0.6875rem] font-semibold text-anil-800 active:bg-anil-100 lg:min-h-11 lg:flex-row lg:gap-2 lg:text-sm">
                        <x-phosphor-magnifying-glass-bold class="size-5" /> Buscar <span class="hidden font-mono text-anil-500 lg:inline">F2</span>
                    </button>
                    <button type="button" x-on:click="lineas.length ? modal = 'espera-guardar' : avisar('No hay nada que guardar.', 'error')" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg border border-anil-200 text-[0.6875rem] font-semibold text-anil-800 active:bg-anil-100 lg:min-h-11 lg:flex-row lg:gap-2 lg:text-sm">
                        <x-phosphor-pause-bold class="size-5" /> Espera <span class="hidden font-mono text-anil-500 lg:inline">F3</span>
                    </button>
                    <button type="button" x-on:click="abrirEsperas()" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg border border-anil-200 text-[0.6875rem] font-semibold text-anil-800 active:bg-anil-100 lg:min-h-11 lg:flex-row lg:gap-2 lg:text-sm">
                        <x-phosphor-clock-counter-clockwise-bold class="size-5" /> Recuperar <span class="hidden font-mono text-anil-500 lg:inline">F4</span>
                    </button>
                    <button type="button" x-on:click="abrirDescuentoGeneral()" x-show="cfg.puedeDescuento" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg border border-anil-200 text-[0.6875rem] font-semibold text-anil-800 active:bg-anil-100 lg:min-h-11 lg:flex-row lg:gap-2 lg:text-sm">
                        <x-phosphor-percent-bold class="size-5" /> Descuento <span class="hidden font-mono text-anil-500 lg:inline">F6</span>
                    </button>
                    <button type="button" x-on:click="abrirClientes()" x-show="cfg.puedeCliente" :class="cliente ? 'border-rosa-300 bg-rosa-50 text-rosa-700' : 'border-anil-200 text-anil-800'" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg border text-[0.6875rem] font-semibold active:bg-anil-100 lg:min-h-11 lg:flex-row lg:gap-2 lg:text-sm">
                        <x-phosphor-user-circle-bold class="size-5" /> Cliente <span class="hidden font-mono text-anil-500 lg:inline">F7</span>
                    </button>
                </div>

                <div x-show="cliente" x-cloak class="mx-2 mt-2 flex items-center justify-between gap-2 rounded-lg bg-rosa-50 px-3 py-2 text-sm lg:mx-4">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-rosa-700" x-text="cliente?.nombre"></p>
                        <p class="text-xs text-anil-700">
                            <span x-text="'Precio ' + (cliente?.lista || 1)"></span>
                            <span x-show="cliente?.credito"> · Crédito disponible <strong x-text="cliente ? dinero(Math.round(parseFloat(cliente.disponible) * 100)) : ''"></strong></span>
                        </p>
                    </div>
                    <button type="button" x-on:click="quitarCliente()" class="grid size-9 shrink-0 place-items-center rounded-lg text-anil-600 hover:bg-white" aria-label="Quitar cliente"><x-phosphor-x-bold class="size-4" /></button>
                </div>

                <div class="flex items-end justify-between gap-3 px-4 pb-1 pt-3 lg:mt-auto lg:flex-col lg:items-stretch lg:gap-1 lg:px-5 lg:pt-5">
                    <dl class="hidden space-y-1 text-sm text-anil-700 lg:block">
                        <div class="flex justify-between"><dt>Artículos</dt><dd class="font-mono" x-text="lineas.length + ' (' + (Math.round(piezas * 1000) / 1000) + ' pzas)'"></dd></div>
                        <div class="flex justify-between" x-show="descuentoTotal > 0" x-cloak><dt>Descuento</dt><dd class="font-mono text-emerald-700" x-text="'−' + dinero(descuentoTotal)"></dd></div>
                    </dl>
                    <div class="flex items-baseline justify-between lg:mt-2">
                        <p class="rotulo">Total</p>
                        <p class="font-display text-4xl font-extrabold leading-none tracking-tight text-anil-900 lg:text-5xl [font-stretch:112%]" x-text="dinero(total)" aria-live="polite"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 px-4 pb-3 lg:flex-col-reverse lg:items-stretch lg:px-5 lg:pb-5">
                    <button type="button" x-on:click="vaciar()" x-show="lineas.length" x-cloak class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold text-peligro hover:bg-red-50">
                        <x-phosphor-trash-bold class="size-4" /> <span class="hidden sm:inline lg:inline">Vaciar</span>
                    </button>
                    <button type="button" x-on:click="abrirCobro()" :disabled="!lineas.length"
                            class="inline-flex min-h-14 flex-1 items-center justify-center gap-3 rounded-xl bg-rosa-500 px-6 font-display text-lg font-extrabold uppercase tracking-wide text-white shadow-[0_4px_0_0_var(--color-rosa-700)] transition [font-stretch:112%] hover:bg-rosa-600 active:translate-y-[4px] active:shadow-none disabled:pointer-events-none disabled:opacity-40">
                        Cobrar <span class="hidden rounded bg-white/20 px-2 py-0.5 font-mono text-xs lg:inline">F8</span>
                    </button>
                </div>
            </aside>
        </div>

        {{-- ═══ Ventanas ═══ --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-anil-950/70" x-on:click="modal !== 'exito' && (modal = null, enfocar())"></div>

            {{-- Buscar por descripción --}}
            <div x-show="modal === 'buscar'" x-cloak class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-anil-100 px-5 py-4">
                    <h2 class="text-lg font-extrabold">Buscar artículo</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </header>
                <div class="border-b border-anil-100 p-4">
                    <input x-ref="buscarTexto" x-model="busqueda.texto" x-on:input.debounce.250ms="buscarTexto()" type="search" placeholder="Escribe parte del nombre o de la clave" autocomplete="off"
                           x-on:keydown.arrow-down.prevent="busqueda.enfocado = Math.min(busqueda.enfocado + 1, busqueda.resultados.length - 1)"
                           x-on:keydown.arrow-up.prevent="busqueda.enfocado = Math.max(busqueda.enfocado - 1, 0)"
                           x-on:keydown.enter.prevent="busqueda.resultados[busqueda.enfocado] && elegirResultado(busqueda.resultados[busqueda.enfocado])"
                           class="block w-full rounded-lg border border-anil-200 px-3.5 py-3 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                </div>
                <ul class="min-h-0 flex-1 divide-y divide-anil-50 overflow-y-auto">
                    <template x-for="(a, i) in busqueda.resultados" :key="a.id">
                        <li>
                            <button type="button" x-on:click="elegirResultado(a)" :class="busqueda.enfocado === i ? 'bg-rosa-50' : ''" class="flex min-h-14 w-full items-center justify-between gap-3 px-5 py-2.5 text-left hover:bg-anil-50">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold" x-text="a.descripcion"></span>
                                    <span class="block font-mono text-xs text-anil-600" x-text="a.clave"></span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-display text-base font-extrabold" x-text="dinero(Math.round(parseFloat(a.precios[0]) * 100))"></span>
                                    <span class="block text-xs text-anil-600" x-show="a.inventariable" x-text="'Existencia ' + (Math.round(parseFloat(a.existencia) * 1000) / 1000)"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                    <li x-show="!busqueda.resultados.length && busqueda.texto.trim().length >= 2 && !busqueda.cargando" class="px-5 py-8 text-center text-sm text-anil-700">
                        No se encontró nada.
                        <button type="button" x-show="cfg.puedeAlta" x-on:click="abrirAlta(busqueda.texto)" class="font-semibold text-rosa-600 underline">Dar de alta este artículo</button>
                    </li>
                    <li x-show="busqueda.texto.trim().length < 2" class="px-5 py-8 text-center text-sm text-anil-600">Escribe al menos 2 letras.</li>
                </ul>
            </div>

            {{-- Peso (granel) --}}
            <div x-show="modal === 'peso'" x-cloak class="relative w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-sm sm:rounded-2xl">
                <h2 class="text-lg font-extrabold" x-text="peso.articulo?.descripcion"></h2>
                <p class="text-sm text-anil-700">Se vende a granel. ¿Cuánto es? <span x-text="peso.articulo ? '(' + peso.articulo.unidad + ')' : ''"></span></p>
                <input x-model="peso.valor" x-on:keydown.enter.prevent="confirmarPeso()" x-effect="modal === 'peso' && $nextTick(() => $el.focus())" type="text" inputmode="decimal" autocomplete="off"
                       class="mt-4 block w-full rounded-lg border-2 border-anil-200 px-4 py-3 text-center font-mono text-3xl focus:border-rosa-500 focus:ring-4 focus:ring-rosa-500/15 focus:outline-none" placeholder="0.000">
                <button type="button" x-show="'serial' in navigator" x-cloak x-on:click="leerBascula()" :disabled="pesoLeyendo"
                        class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-anil-300 text-sm font-semibold text-anil-700 hover:bg-anil-50 disabled:opacity-50">
                    <x-phosphor-scales-bold class="size-4" /> <span x-text="pesoLeyendo ? 'Leyendo…' : 'Leer de la báscula'"></span>
                </button>
                <p x-show="pesoError" x-text="pesoError" x-cloak class="mt-2 text-sm font-medium text-peligro" role="alert"></p>
                <div class="mt-4 flex justify-end gap-3">
                    <x-secondary-button x-on:click="modal = null; enfocar()">Cancelar</x-secondary-button>
                    <x-primary-button type="button" x-on:click="confirmarPeso()">Agregar</x-primary-button>
                </div>
            </div>

            {{-- Cliente --}}
            <div x-show="modal === 'cliente'" x-cloak class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-anil-100 px-5 py-4">
                    <h2 class="text-lg font-extrabold">Elegir cliente</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </header>
                <div class="border-b border-anil-100 p-4">
                    <input x-ref="clienteTexto" x-model="clientes.texto" x-on:input.debounce.250ms="buscarClientes()" type="search" placeholder="Nombre o teléfono del cliente" autocomplete="off"
                           class="block w-full rounded-lg border border-anil-200 px-3.5 py-3 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                </div>
                <ul class="min-h-0 flex-1 divide-y divide-anil-50 overflow-y-auto">
                    <template x-for="c in clientes.resultados" :key="c.id">
                        <li>
                            <button type="button" x-on:click="elegirCliente(c)" class="flex min-h-14 w-full items-center justify-between gap-3 px-5 py-2.5 text-left hover:bg-anil-50">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold" x-text="c.nombre"></span>
                                    <span class="block text-xs text-anil-600" x-text="(c.telefono || 'Sin teléfono') + ' · Precio ' + c.lista"></span>
                                </span>
                                <span class="shrink-0 text-right text-xs" x-show="c.credito">
                                    <span class="block text-anil-600">Disponible</span>
                                    <span class="block font-display text-sm font-extrabold" x-text="dinero(Math.round(parseFloat(c.disponible) * 100))"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                    <li x-show="!clientes.resultados.length && clientes.texto.trim().length >= 2 && !clientes.cargando" class="px-5 py-8 text-center text-sm text-anil-700">No se encontró ningún cliente.</li>
                    <li x-show="clientes.texto.trim().length < 2" class="px-5 py-8 text-center text-sm text-anil-600">Escribe al menos 2 letras.</li>
                </ul>
                <footer class="border-t border-anil-100 p-3" x-show="cliente">
                    <button type="button" x-on:click="quitarCliente()" class="min-h-11 w-full rounded-lg text-sm font-semibold text-peligro hover:bg-red-50">Quitar el cliente de esta venta</button>
                </footer>
            </div>

            {{-- Descuento general --}}
            <div x-show="modal === 'descuento'" x-cloak class="relative w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-sm sm:rounded-2xl">
                <h2 class="text-lg font-extrabold">Descuento a toda la venta</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" x-on:click="descGlobal.tipo = 'pct'" :class="descGlobal.tipo === 'pct' ? 'border-rosa-500 bg-rosa-50 text-rosa-700' : 'border-anil-200'" class="min-h-11 rounded-lg border font-semibold">Porcentaje %</button>
                    <button type="button" x-on:click="descGlobal.tipo = 'monto'" :class="descGlobal.tipo === 'monto' ? 'border-rosa-500 bg-rosa-50 text-rosa-700' : 'border-anil-200'" class="min-h-11 rounded-lg border font-semibold">Monto $</button>
                </div>
                <input x-ref="descValor" x-model="descGlobal.valor" x-on:keydown.enter.prevent="aplicarDescuentoGeneral()" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                       class="mt-3 block w-full rounded-lg border-2 border-anil-200 px-4 py-3 text-center font-mono text-2xl focus:border-rosa-500 focus:ring-4 focus:ring-rosa-500/15 focus:outline-none">
                <p class="mt-2 text-sm text-anil-700" x-show="descuentoGeneral > 0">Se descuentan <strong x-text="dinero(descuentoGeneral)"></strong> del total.</p>
                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <button type="button" x-on:click="quitarDescuentoGeneral()" class="mr-auto min-h-11 rounded-lg px-3 text-sm font-semibold text-peligro hover:bg-red-50">Quitar descuento</button>
                    <x-secondary-button x-on:click="modal = null; enfocar()">Cancelar</x-secondary-button>
                    <x-primary-button type="button" x-on:click="aplicarDescuentoGeneral()">Aplicar</x-primary-button>
                </div>
            </div>

            {{-- Descuento de una partida --}}
            <div x-show="modal === 'descLinea'" x-cloak class="relative w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-sm sm:rounded-2xl">
                <h2 class="text-lg font-extrabold">Descuento al artículo</h2>
                <p class="truncate text-sm text-anil-700" x-text="lineas[descLinea.indice]?.descripcion"></p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" x-on:click="descLinea.tipo = 'pct'" :class="descLinea.tipo === 'pct' ? 'border-rosa-500 bg-rosa-50 text-rosa-700' : 'border-anil-200'" class="min-h-11 rounded-lg border font-semibold">Porcentaje %</button>
                    <button type="button" x-on:click="descLinea.tipo = 'monto'" :class="descLinea.tipo === 'monto' ? 'border-rosa-500 bg-rosa-50 text-rosa-700' : 'border-anil-200'" class="min-h-11 rounded-lg border font-semibold">Monto $</button>
                </div>
                <input x-ref="descLineaValor" x-model="descLinea.valor" x-on:keydown.enter.prevent="aplicarDescuentoLinea()" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                       class="mt-3 block w-full rounded-lg border-2 border-anil-200 px-4 py-3 text-center font-mono text-2xl focus:border-rosa-500 focus:ring-4 focus:ring-rosa-500/15 focus:outline-none">
                <div class="mt-4 flex justify-end gap-3">
                    <x-secondary-button x-on:click="modal = null; enfocar()">Cancelar</x-secondary-button>
                    <x-primary-button type="button" x-on:click="aplicarDescuentoLinea()">Aplicar</x-primary-button>
                </div>
                <p class="mt-2 text-xs text-anil-600">Deja el valor vacío o en 0 para quitar el descuento.</p>
            </div>

            {{-- Guardar en espera --}}
            <div x-show="modal === 'espera-guardar'" x-cloak class="relative w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-sm sm:rounded-2xl">
                <h2 class="text-lg font-extrabold">Dejar esta venta en espera</h2>
                <p class="text-sm text-anil-700">Atiende a otro cliente y recupérala después con <kbd class="rounded bg-anil-100 px-1.5 py-0.5 font-mono text-xs">F4</kbd>.</p>
                <input x-model="esperas.nombre" x-on:keydown.enter.prevent="guardarEnEspera()" x-effect="modal === 'espera-guardar' && $nextTick(() => $el.focus())" type="text" maxlength="60" autocomplete="off" placeholder="Nombre o seña del cliente (opcional)"
                       class="mt-4 block w-full rounded-lg border border-anil-200 px-3.5 py-3 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                <div class="mt-4 flex justify-end gap-3">
                    <x-secondary-button x-on:click="modal = null; enfocar()">Cancelar</x-secondary-button>
                    <x-primary-button type="button" x-on:click="guardarEnEspera()">Guardar en espera</x-primary-button>
                </div>
            </div>

            {{-- Ventas en espera --}}
            <div x-show="modal === 'esperas'" x-cloak class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-anil-100 px-5 py-4">
                    <h2 class="text-lg font-extrabold">Ventas en espera</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </header>
                <ul class="min-h-0 flex-1 divide-y divide-anil-50 overflow-y-auto">
                    <template x-for="e in esperas.lista" :key="e.id">
                        <li class="flex items-center gap-2 px-5 py-3">
                            <button type="button" x-on:click="recuperarEspera(e.id)" class="min-h-12 min-w-0 flex-1 text-left">
                                <span class="block truncate font-semibold" x-text="e.nombre || 'Sin nombre'"></span>
                                <span class="text-xs text-anil-600" x-text="e.piezas + ' artículos · ' + e.hora + ' · ' + (e.usuario || '')"></span>
                            </button>
                            <button type="button" x-on:click="recuperarEspera(e.id)" class="rounded-lg bg-anil-900 px-3 py-2 text-sm font-semibold text-white">Recuperar</button>
                            <button type="button" x-on:click="descartarEspera(e.id)" class="grid size-10 place-items-center rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Descartar"><x-phosphor-trash-bold class="size-4" /></button>
                        </li>
                    </template>
                    <li x-show="!esperas.lista.length && !esperas.cargando" class="px-5 py-10 text-center text-sm text-anil-700">No hay ventas en espera.</li>
                    <li x-show="esperas.cargando" class="px-5 py-10 text-center text-sm text-anil-600">Cargando…</li>
                </ul>
            </div>

            {{-- Alta rápida --}}
            <div x-show="modal === 'alta'" x-cloak class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-md sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-anil-100 px-5 py-4">
                    <h2 class="text-lg font-extrabold">Alta rápida de artículo</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </header>
                <form x-on:submit.prevent="guardarAlta()" class="space-y-4 overflow-y-auto px-5 py-5">
                    <div><label class="rotulo mb-1.5 block" for="alta_clave">Clave</label>
                        <input id="alta_clave" x-model="alta.clave" type="text" maxlength="40" required autocomplete="off" class="block w-full rounded-lg border border-anil-200 px-3.5 py-2.5 font-mono text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none"></div>
                    <div><label class="rotulo mb-1.5 block" for="alta_desc">Descripción</label>
                        <input id="alta_desc" x-ref="altaDescripcion" x-model="alta.descripcion" type="text" maxlength="150" required autocomplete="off" class="block w-full rounded-lg border border-anil-200 px-3.5 py-2.5 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="rotulo mb-1.5 block" for="alta_precio">Precio al público</label>
                            <input id="alta_precio" x-model="alta.precio" type="text" inputmode="decimal" required autocomplete="off" class="block w-full rounded-lg border border-anil-200 px-3.5 py-2.5 font-mono text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none"></div>
                        <div><label class="rotulo mb-1.5 block" for="alta_iva">IVA</label>
                            <select id="alta_iva" x-model="alta.iva_id" class="block w-full rounded-lg border border-anil-200 py-2.5 pl-3.5 pr-9 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                                <option value="">Sin IVA</option>
                                @foreach ($this->ivas as $i)<option value="{{ $i->id }}">{{ $i->nombre }}</option>@endforeach
                            </select></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="rotulo mb-1.5 block" for="alta_depto">Departamento</label>
                            <select id="alta_depto" x-model="alta.departamento_id" required class="block w-full rounded-lg border border-anil-200 py-2.5 pl-3.5 pr-9 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                                @foreach ($this->departamentos as $d)<option value="{{ $d->id }}">{{ $d->nombre }}</option>@endforeach
                            </select></div>
                        <div><label class="rotulo mb-1.5 block" for="alta_unidad">Unidad</label>
                            <select id="alta_unidad" x-model="alta.unidad_medida_id" required class="block w-full rounded-lg border border-anil-200 py-2.5 pl-3.5 pr-9 text-base focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
                                @foreach ($this->unidades as $u)<option value="{{ $u->id }}">{{ $u->nombre }}</option>@endforeach
                            </select></div>
                    </div>
                    <p x-show="alta.error" x-text="alta.error" class="text-sm font-medium text-peligro" role="alert"></p>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button x-on:click="modal = null; enfocar()">Cancelar</x-secondary-button>
                        <x-primary-button x-bind:disabled="alta.guardando">Crear y agregar</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Cobro --}}
            <div x-show="modal === 'cobro'" x-cloak class="relative flex max-h-[96dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-anil-100 px-5 py-3">
                    <h2 class="text-lg font-extrabold">Cobrar</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </header>

                <div class="overflow-y-auto px-5 py-4">
                    <p class="rotulo text-center">Total a cobrar</p>
                    <p class="text-center font-display text-5xl font-extrabold tracking-tight text-anil-900 [font-stretch:112%]" x-text="dinero(totalCobro)"></p>

                    <p x-show="cobro.error" x-text="cobro.error" class="mt-3 rounded-lg bg-red-50 px-3 py-2.5 text-sm font-medium text-red-800 ring-1 ring-red-200" role="alert"></p>

                    <div class="mt-4 space-y-3" x-show="cobro.totalServidor !== null">
                        <template x-for="(p, i) in cobro.pagos" :key="i">
                            <div class="rounded-xl border border-anil-200 p-3">
                                <div class="flex items-center gap-2">
                                    <select x-model="p.forma" x-on:change="p.auto = false; sincronizarEfectivo()" class="rounded-lg border border-anil-200 py-2 pl-3 pr-8 text-sm font-semibold focus:border-anil-500 focus:ring-2 focus:ring-anil-500/20" aria-label="Forma de pago">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="tarjeta">Tarjeta</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="credito" x-show="cliente?.credito" :disabled="!cliente?.credito">A crédito</option>
                                    </select>
                                    <input x-ref="montoCobro" x-model="p.monto" x-on:input="p.forma === 'efectivo' ? (p.auto = false) : sincronizarEfectivo()" type="text" inputmode="decimal" autocomplete="off" aria-label="Monto"
                                           class="min-w-0 flex-1 rounded-lg border-2 border-anil-200 px-3 py-2 text-right font-mono text-xl font-bold focus:border-rosa-500 focus:ring-4 focus:ring-rosa-500/15 focus:outline-none">
                                    <button type="button" x-show="cobro.pagos.length > 1" x-on:click="quitarPago(i)" class="grid size-10 shrink-0 place-items-center rounded-lg text-anil-500 hover:bg-red-50 hover:text-red-700" aria-label="Quitar este pago"><x-phosphor-x-bold class="size-4" /></button>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5" x-show="p.forma === 'efectivo'">
                                    <button type="button" x-on:click="fijarEfectivo(efectivoNecesario)" class="min-h-9 rounded-lg bg-anil-100 px-3 text-sm font-semibold text-anil-800 active:bg-anil-200">Exacto</button>
                                    <template x-for="b in billetes" :key="b">
                                        <button type="button" x-on:click="fijarEfectivo(b)" class="min-h-9 rounded-lg bg-anil-100 px-3 font-mono text-sm font-semibold text-anil-800 active:bg-anil-200" x-text="dinero(b).replace('.00', '')"></button>
                                    </template>
                                </div>
                                <input x-show="p.forma !== 'efectivo'" x-model="p.referencia" type="text" maxlength="60" placeholder="Referencia o autorización (opcional)" autocomplete="off"
                                       class="mt-2 block w-full rounded-lg border border-anil-200 px-3 py-2 text-sm focus:border-anil-500 focus:ring-2 focus:ring-anil-500/20">
                            </div>
                        </template>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" x-on:click="agregarPago('tarjeta')" class="min-h-10 rounded-lg border border-dashed border-anil-300 px-3 text-sm font-semibold text-anil-700 hover:bg-anil-50">+ Tarjeta</button>
                            <button type="button" x-on:click="agregarPago('transferencia')" class="min-h-10 rounded-lg border border-dashed border-anil-300 px-3 text-sm font-semibold text-anil-700 hover:bg-anil-50">+ Transferencia</button>
                            <button type="button" x-on:click="agregarPago('credito')" x-show="cliente?.credito && !cobro.pagos.some(p => p.forma === 'credito')" class="min-h-10 rounded-lg border border-dashed border-rosa-300 px-3 text-sm font-semibold text-rosa-700 hover:bg-rosa-50">+ A crédito</button>
                            <button type="button" x-on:click="agregarPago('efectivo')" x-show="!cobro.pagos.some(p => p.forma === 'efectivo')" class="min-h-10 rounded-lg border border-dashed border-anil-300 px-3 text-sm font-semibold text-anil-700 hover:bg-anil-50">+ Efectivo</button>
                        </div>

                        <div class="rounded-xl px-4 py-3" :class="faltante > 0 ? 'bg-red-50' : 'bg-emerald-50'">
                            <template x-if="faltante > 0">
                                <p class="flex items-baseline justify-between font-semibold text-red-800"><span>Falta</span><span class="font-display text-2xl font-extrabold" x-text="dinero(faltante)"></span></p>
                            </template>
                            <template x-if="faltante === 0 && sinEfectivo <= totalCobro">
                                <p class="flex items-baseline justify-between font-semibold text-emerald-900"><span>Cambio</span><span class="font-display text-3xl font-extrabold" x-text="dinero(cambio)"></span></p>
                            </template>
                            <p x-show="sinEfectivo > totalCobro" class="text-sm font-semibold text-red-800">Los pagos con tarjeta, transferencia o crédito no pueden pasar del total.</p>
                            <p x-show="creditoUsado > (cobro.disponible || 0)" class="text-sm font-semibold text-red-800">Excede el crédito disponible del cliente (<span x-text="dinero(cobro.disponible || 0)"></span>).</p>
                            <p x-show="efectivoNecesario === 0 && efectivoRecibido > 0" class="text-sm font-semibold text-red-800">El total ya está cubierto: quita el efectivo.</p>
                        </div>
                    </div>
                    <p x-show="cobro.procesando && cobro.totalServidor === null" class="mt-6 text-center text-sm text-anil-700">Calculando…</p>
                </div>

                <footer class="border-t border-anil-100 p-4">
                    <button type="button" x-on:click="confirmarCobro()" :disabled="!puedeConfirmar"
                            class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-rosa-500 font-display text-lg font-extrabold uppercase tracking-wide text-white shadow-[0_4px_0_0_var(--color-rosa-700)] transition [font-stretch:112%] hover:bg-rosa-600 active:translate-y-[4px] active:shadow-none disabled:pointer-events-none disabled:opacity-40">
                        <span x-show="!cobro.procesando">Confirmar cobro</span>
                        <span x-show="cobro.procesando">Procesando…</span>
                    </button>
                </footer>
            </div>

            {{-- Venta lista --}}
            <div x-show="modal === 'exito'" x-cloak class="relative w-full rounded-t-2xl bg-white p-6 text-center shadow-2xl sm:max-w-sm sm:rounded-2xl">
                <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-emerald-700"><x-phosphor-check-bold class="size-8" /></div>
                <h2 class="mt-3 font-display text-2xl font-extrabold">Venta registrada</h2>
                <p class="font-mono text-sm text-anil-700" x-text="exito?.folio"></p>
                <template x-if="exito && parseFloat(exito.cambio) > 0">
                    <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3">
                        <p class="rotulo !text-emerald-800">Entrega de cambio</p>
                        <p class="font-display text-5xl font-extrabold text-emerald-900" x-text="dinero(Math.round(parseFloat(exito.cambio) * 100))"></p>
                    </div>
                </template>
                <p class="mt-3 text-sm text-anil-700">Total <strong x-text="exito ? dinero(Math.round(parseFloat(exito.total) * 100)) : ''"></strong></p>
                <div class="mt-5 grid gap-2">
                    <x-primary-button type="button" x-on:click="nuevaVenta()" class="w-full">Nueva venta <span class="font-mono text-xs opacity-80">Enter</span></x-primary-button>
                    <x-secondary-button x-on:click="imprimirTicket(exito.id)" class="w-full"><x-phosphor-printer-bold class="size-4" /> Imprimir ticket</x-secondary-button>
                </div>
            </div>

            {{-- Atajos --}}
            <div x-show="modal === 'atajos'" x-cloak class="relative w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-md sm:rounded-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-extrabold">Atajos de teclado</h2>
                    <button type="button" x-on:click="modal = null; enfocar()" class="grid size-10 place-items-center rounded-lg text-anil-600 hover:bg-anil-50" aria-label="Cerrar"><x-phosphor-x-bold class="size-5" /></button>
                </div>
                <dl class="mt-3 divide-y divide-anil-50 text-sm">
                    @foreach ([
                        ['Enter', 'Agregar el artículo escaneado o escrito'],
                        ['5*  y luego el código', 'Vender 5 piezas del artículo (o escribe 5*código)'],
                        ['F2', 'Buscar un artículo por nombre'],
                        ['F3', 'Dejar la venta en espera'],
                        ['F4', 'Recuperar una venta en espera'],
                        ['F6', 'Descuento a toda la venta'],
                        ['F7', 'Elegir cliente (su lista de precios y su crédito)'],
                        ['F8  o  Ctrl+Enter', 'Cobrar'],
                        ['↑  ↓', 'Moverte entre los artículos'],
                        ['+  y  −', 'Subir o bajar la cantidad seleccionada'],
                        ['Supr', 'Quitar el artículo seleccionado'],
                        ['Esc', 'Cerrar una ventana o borrar el multiplicador'],
                    ] as [$tecla, $texto])
                        <div class="flex items-center justify-between gap-4 py-2"><dt class="shrink-0 rounded bg-anil-100 px-2 py-1 font-mono text-xs font-bold text-anil-900">{{ $tecla }}</dt><dd class="text-right text-anil-800">{{ $texto }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</div>
