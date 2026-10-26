<?php

namespace App\Actions\Ventas;

use App\Actions\Clientes\RegistrarMovimientoCliente;
use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\FormaPago;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Impuesto;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\CalculadoraPrecios;
use App\Services\CalculadoraVenta;
use App\Services\Folios;
use App\Support\Numero;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta de forma atómica: venta + partidas + pagos + movimientos de inventario.
 *
 * Nunca se confía en los precios que manda la pantalla: se leen de la base de datos
 * (con el artículo bloqueado) y se guardan como "foto" en cada partida.
 */
class RegistrarVenta
{
    public function __construct(
        private readonly RegistrarMovimientoInventario $movimiento,
        private readonly RegistrarMovimientoCliente $cuenta,
    ) {}

    /**
     * @param  list<array{articulo_id: int|string, cantidad: string, descuento_tipo?: ?string, descuento_valor?: ?string}>  $lineas
     * @param  list<array{forma: string, monto: string, referencia?: ?string}>  $pagos
     * @param  array{tipo?: ?string, valor?: ?string}  $descuentoGlobal
     *
     * @throws ValidationException
     */
    public function __invoke(Caja $caja, User $usuario, array $lineas, array $pagos, array $descuentoGlobal = [], int $lista = 1, ?Cliente $cliente = null): Venta
    {
        $this->autorizar($usuario, $lineas, $descuentoGlobal);
        $this->validar($caja, $lineas, $pagos, $descuentoGlobal, $lista);

        return DB::transaction(function () use ($caja, $usuario, $lineas, $pagos, $descuentoGlobal, $lista, $cliente) {
            // Solo se vende con el turno propio abierto en esa caja; se bloquea para que no se cierre a medio cobro.
            $turno = Turno::query()->abiertos()
                ->where('caja_id', $caja->id)->where('user_id', $usuario->id)
                ->lockForUpdate()->first();

            if (! $turno) {
                throw ValidationException::withMessages(['turno' => 'Abre tu turno de caja antes de vender.']);
            }

            // El cliente se bloquea (su saldo puede cambiar) y su lista de precios manda sobre la elegida.
            if ($cliente) {
                $cliente = Cliente::query()->lockForUpdate()->find($cliente->id);

                if (! $cliente || ! $cliente->activo) {
                    throw ValidationException::withMessages(['cliente' => 'Ese cliente ya no está disponible.']);
                }

                $lista = $cliente->lista_precios;
            } else {
                // Sin cliente solo existe el precio 1: el mayoreo es una decisión del dueño (lista del cliente).
                $lista = 1;
            }

            // Bloqueo en orden fijo para que dos ventas simultáneas no se crucen.
            $ids = collect($lineas)->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $articulos = Articulo::query()->with(['impuestos', 'unidad'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $this->validarArticulos($articulos, $lineas);

            $entrada = $this->entradaCalculo($articulos, $lineas, $lista);

            $calculo = CalculadoraVenta::calcular($entrada, [
                'tipo' => $descuentoGlobal['tipo'] ?? null,
                'valor' => isset($descuentoGlobal['valor']) ? (string) $descuentoGlobal['valor'] : null,
            ]);

            $total = BigDecimal::of($calculo['total']);

            if (! $total->isPositive()) {
                throw ValidationException::withMessages(['total' => 'El total de la venta debe ser mayor que cero.']);
            }

            [$pagosAplicados, $recibido, $cambio, $credito] = $this->resolverPagos($pagos, $total);
            $this->validarCredito($cliente, $credito);

            $this->validarExistencias($articulos, $lineas);

            $numero = Folios::siguiente($caja->serie);

            $venta = Venta::query()->create([
                'serie' => $caja->serie,
                'numero' => $numero,
                'folio' => Folios::formato($caja->serie, $numero),
                'caja_id' => $caja->id,
                'turno_id' => $turno->id,
                'user_id' => $usuario->id,
                'cliente_id' => $cliente?->id,
                'estado' => 'completada',
                'lista_precios' => $lista,
                'subtotal' => $calculo['subtotal'],
                'descuento' => $calculo['descuento'],
                'impuestos' => $calculo['impuestos'],
                'total' => $calculo['total'],
                'efectivo_recibido' => (string) $recibido,
                'cambio' => (string) $cambio,
            ]);

            foreach ($lineas as $i => $l) {
                $a = $articulos[(int) $l['articulo_id']];
                $c = $calculo['lineas'][$i];

                $partida = $venta->partidas()->create([
                    'articulo_id' => $a->id,
                    'clave' => $a->clave,
                    'descripcion' => $a->descripcion,
                    'unidad' => $a->unidad->abreviatura,
                    'cantidad' => $entrada[$i]['cantidad'],
                    'precio_unitario' => $entrada[$i]['precio'],
                    'descuento' => $c['descuento'],
                    'costo_unitario' => (string) $a->costo,
                    'tasa_impuestos' => $entrada[$i]['tasa'],
                    'subtotal' => $c['subtotal'],
                    'impuestos' => $c['impuestos'],
                    'total' => $c['total'],
                    'impuestos_detalle' => $c['impuestos_detalle'],
                ]);

                ($this->movimiento)(
                    $a,
                    TipoMovimiento::Venta,
                    (string) BigDecimal::of($entrada[$i]['cantidad'])->negated(),
                    (string) $a->costo,
                    $usuario,
                    $venta,
                );
            }

            foreach ($pagosAplicados as $p) {
                $venta->pagos()->create($p);
            }

            if ($credito->isPositive()) {
                ($this->cuenta)($cliente, 'cargo', (string) $credito, $usuario, $venta, $turno->id);
            }

            return $venta->load(['partidas', 'pagos', 'caja', 'cliente']);
        });
    }

    /**
     * Vista previa para la pantalla de cobro: mismos precios, impuestos y redondeos que
     * la venta real, pero sin guardar nada ni tocar el inventario.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $descuentoGlobal
     * @return array{subtotal: string, descuento: string, impuestos: string, total: string, lineas: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function calcular(User $usuario, array $lineas, array $descuentoGlobal = [], int $lista = 1, ?Cliente $cliente = null): array
    {
        $lista = $cliente?->lista_precios ?? 1;
        $this->autorizar($usuario, $lineas, $descuentoGlobal);
        $this->validarLineas($lineas, $descuentoGlobal, $lista);

        $ids = collect($lineas)->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->values();
        $articulos = Articulo::query()->with(['impuestos', 'unidad'])->whereIn('id', $ids)->get()->keyBy('id');

        $this->validarArticulos($articulos, $lineas);

        $calculo = CalculadoraVenta::calcular($this->entradaCalculo($articulos, $lineas, $lista), [
            'tipo' => $descuentoGlobal['tipo'] ?? null,
            'valor' => isset($descuentoGlobal['valor']) ? (string) $descuentoGlobal['valor'] : null,
        ]);

        if (! BigDecimal::of($calculo['total'])->isPositive()) {
            throw ValidationException::withMessages(['total' => 'El total de la venta debe ser mayor que cero.']);
        }

        return $calculo;
    }

    /**
     * @param  Collection<int, Articulo>  $articulos
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function entradaCalculo($articulos, array $lineas, int $lista): array
    {
        $entrada = [];

        foreach ($lineas as $l) {
            $a = $articulos[(int) $l['articulo_id']];
            $entrada[] = [
                'precio' => $this->precio($a, $lista),
                'cantidad' => (string) BigDecimal::of(trim((string) $l['cantidad']))->toScale(3, RoundingMode::HalfUp),
                'tasa' => $a->tasaImpuestos(),
                'impuestos' => $a->impuestos->map(fn (Impuesto $i) => ['nombre' => $i->nombre, 'tasa' => $i->tasaAplicable()])->all(),
                'descuento_tipo' => $l['descuento_tipo'] ?? null,
                'descuento_valor' => isset($l['descuento_valor']) ? (string) $l['descuento_valor'] : null,
            ];
        }

        return $entrada;
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function autorizar(User $usuario, array $lineas, array $descuentoGlobal): void
    {
        abort_unless($usuario->can('ventas.cobrar'), 403, 'No tienes permiso para cobrar ventas.');

        $conDescuento = collect($lineas)->contains(fn ($l) => ! empty($l['descuento_tipo']) && ! empty($l['descuento_valor']) && (float) $l['descuento_valor'] > 0)
            || (! empty($descuentoGlobal['tipo']) && ! empty($descuentoGlobal['valor']) && (float) $descuentoGlobal['valor'] > 0);

        if ($conDescuento && ! $usuario->can('ventas.descuento')) {
            throw ValidationException::withMessages(['descuento' => 'No tienes permiso para aplicar descuentos.']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $pagos
     *
     * @throws ValidationException
     */
    private function validar(Caja $caja, array $lineas, array $pagos, array $descuentoGlobal, int $lista): void
    {
        if (! $caja->activa || $caja->trashed()) {
            throw ValidationException::withMessages(['caja' => 'Esta caja está desactivada.']);
        }

        $this->validarLineas($lineas, $descuentoGlobal, $lista);

        Validator::make(
            ['pagos' => $pagos],
            [
                'pagos' => ['required', 'array', 'min:1', 'max:6'],
                'pagos.*.forma' => ['required', Rule::in(FormaPago::valores())],
                'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
                'pagos.*.referencia' => ['nullable', 'string', 'max:60'],
            ],
            [
                'pagos.required' => 'Indica con qué se paga.',
                'pagos.*.monto.gt' => 'Cada pago debe ser mayor que cero.',
            ],
        )->validate();
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $descuentoGlobal
     *
     * @throws ValidationException
     */
    private function validarLineas(array $lineas, array $descuentoGlobal, int $lista): void
    {
        Validator::make(
            ['lineas' => $lineas, 'descuento_global' => $descuentoGlobal, 'lista' => $lista],
            [
                'lineas' => ['required', 'array', 'min:1', 'max:300'],
                'lineas.*.articulo_id' => ['required', Rule::exists('articulos', 'id')->whereNull('deleted_at')->where('activo', true)],
                'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999'],
                'lineas.*.descuento_tipo' => ['nullable', Rule::in(['pct', 'monto'])],
                'lineas.*.descuento_valor' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'descuento_global.tipo' => ['nullable', Rule::in(['pct', 'monto'])],
                'descuento_global.valor' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'lista' => ['required', Rule::in([1, 2, 3])],
            ],
            [
                'lineas.required' => 'Agrega al menos un artículo a la venta.',
                'lineas.min' => 'Agrega al menos un artículo a la venta.',
                'lineas.*.articulo_id.exists' => 'Uno de los artículos ya no está disponible.',
                'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            ],
        )->after(function ($v) use ($lineas, $descuentoGlobal) {
            foreach ($lineas as $i => $l) {
                if (($l['descuento_tipo'] ?? null) === 'pct' && is_numeric($l['descuento_valor'] ?? null) && (float) $l['descuento_valor'] > 100) {
                    $v->errors()->add("lineas.$i.descuento_valor", 'El descuento no puede ser mayor al 100%.');
                }
            }

            if (($descuentoGlobal['tipo'] ?? null) === 'pct' && is_numeric($descuentoGlobal['valor'] ?? null) && (float) $descuentoGlobal['valor'] > 100) {
                $v->errors()->add('descuento_global.valor', 'El descuento no puede ser mayor al 100%.');
            }
        })->validate();
    }

    /** @param  Collection<int, Articulo>  $articulos */
    private function validarArticulos($articulos, array $lineas): void
    {
        foreach ($lineas as $i => $l) {
            $a = $articulos[(int) $l['articulo_id']] ?? null;

            if (! $a || ! $a->activo) {
                throw ValidationException::withMessages(["lineas.$i.articulo_id" => 'Uno de los artículos ya no está disponible.']);
            }

            if (! $a->unidad->permite_decimales && CalculadoraPrecios::tieneDecimales((string) $l['cantidad'])) {
                throw ValidationException::withMessages(["lineas.$i.cantidad" => "«{$a->descripcion}» solo se vende por piezas completas."]);
            }

            if (BigDecimal::of(trim((string) $l['cantidad']))->toScale(3, RoundingMode::HalfUp)->isZero()) {
                throw ValidationException::withMessages(["lineas.$i.cantidad" => 'La cantidad es demasiado pequeña (mínimo 0.001).']);
            }
        }
    }

    /** Con la opción apagada no se puede vender más de lo que hay. */
    private function validarExistencias($articulos, array $lineas): void
    {
        if (Configuracion::actual()->vender_sin_existencia) {
            return;
        }

        $pedido = [];
        foreach ($lineas as $l) {
            $id = (int) $l['articulo_id'];
            $pedido[$id] = ($pedido[$id] ?? BigDecimal::zero())->plus(trim((string) $l['cantidad']));
        }

        foreach ($pedido as $id => $cantidad) {
            $a = $articulos[$id];

            if ($a->inventariable && BigDecimal::of((string) $a->existencia)->isLessThan($cantidad)) {
                throw ValidationException::withMessages([
                    'existencia' => "No hay suficiente existencia de «{$a->descripcion}» (hay ".Numero::cantidad($a->existencia).').',
                ]);
            }
        }
    }

    private function precio(Articulo $articulo, int $lista): string
    {
        $precio = match ($lista) {
            2 => $articulo->precio_2,
            3 => $articulo->precio_3,
            default => $articulo->precio_1,
        };

        return (string) ($precio ?? $articulo->precio_1);
    }

    /**
     * Reparte los pagos: tarjeta y transferencia se cobran exactos; el efectivo cubre el resto
     * y lo que sobre se regresa como cambio.
     *
     * @param  list<array{forma: string, monto: string, referencia?: ?string}>  $pagos
     * @return array{0: list<array<string, mixed>>, 1: BigDecimal, 2: BigDecimal, 3: BigDecimal} pagos, efectivo recibido, cambio y total a crédito
     *
     * @throws ValidationException
     */
    private function resolverPagos(array $pagos, BigDecimal $total): array
    {
        $sinEfectivo = BigDecimal::zero();
        $recibido = BigDecimal::zero();
        $credito = BigDecimal::zero()->toScale(2);
        $aplicados = [];

        foreach ($pagos as $p) {
            $monto = BigDecimal::of((string) $p['monto'])->toScale(2, RoundingMode::HalfUp);

            if ($p['forma'] === 'efectivo') {
                $recibido = $recibido->plus($monto);

                continue;
            }

            if ($p['forma'] === 'credito') {
                $credito = $credito->plus($monto);
            }

            $sinEfectivo = $sinEfectivo->plus($monto);
            $aplicados[] = ['forma' => $p['forma'], 'monto' => (string) $monto, 'referencia' => ($p['referencia'] ?? null) ?: null];
        }

        if ($sinEfectivo->isGreaterThan($total)) {
            throw ValidationException::withMessages(['pagos' => 'Los pagos con tarjeta, transferencia o crédito no pueden ser mayores al total.']);
        }

        $efectivoAplicado = $total->minus($sinEfectivo);

        if ($recibido->isLessThan($efectivoAplicado)) {
            throw ValidationException::withMessages(['pagos' => 'Falta dinero: todavía faltan $'.$efectivoAplicado->minus($recibido).' por cobrar.']);
        }

        if ($efectivoAplicado->isZero() && $recibido->isPositive()) {
            throw ValidationException::withMessages(['pagos' => 'El total ya está cubierto con tarjeta o transferencia; quita el efectivo.']);
        }

        if ($efectivoAplicado->isPositive()) {
            array_unshift($aplicados, ['forma' => 'efectivo', 'monto' => (string) $efectivoAplicado, 'referencia' => null]);
        }

        return [$aplicados, $recibido, $recibido->minus($efectivoAplicado), $credito];
    }

    /** El crédito exige un cliente activo con límite y saldo suficiente (el cliente ya viene bloqueado). */
    private function validarCredito(?Cliente $cliente, BigDecimal $credito): void
    {
        if (! $credito->isPositive()) {
            return;
        }

        if (! $cliente) {
            throw ValidationException::withMessages(['pagos' => 'Para vender a crédito elige primero al cliente.']);
        }

        if (! $cliente->tieneCredito()) {
            throw ValidationException::withMessages(['pagos' => "{$cliente->nombre} no tiene crédito autorizado."]);
        }

        if ($credito->isGreaterThan($cliente->disponible())) {
            throw ValidationException::withMessages(['pagos' => 'Excede el crédito disponible de '.$cliente->nombre.': le quedan $'.$cliente->disponible().'.']);
        }
    }
}
