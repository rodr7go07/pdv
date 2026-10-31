<?php

namespace App\Actions\Ventas;

use App\Actions\Clientes\RegistrarMovimientoCliente;
use App\Actions\Inventario\RegistrarMovimientoInventario;
use App\Enums\TipoMovimiento;
use App\Models\Cliente;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela una venta (no se borra): regresa el inventario y deja motivo, usuario y fecha.
 * Una venta con devoluciones ya no se puede cancelar.
 *
 *  - Con el turno de la venta todavía abierto se cancela de verdad: la venta ya no cuenta en el corte.
 *  - Con el turno ya cerrado el corte no se toca: se registra una devolución total (el dinero sale de la caja
 *    abierta de quien cancela) marcada como cancelación. Solo lo hace quien tiene el permiso `ventas.cancelar`.
 *  - Quien no tiene ese permiso (un cajero) solo puede cancelar su propia venta con el turno abierto y con la
 *    autorización de un supervisor (`ventas.autorizar_cancelacion`), que debe venir ya verificada.
 */
class CancelarVenta
{
    public function __construct(
        private readonly RegistrarMovimientoInventario $movimiento,
        private readonly RegistrarMovimientoCliente $cuenta,
        private readonly RegistrarDevolucion $devolver,
    ) {}

    public function __invoke(Venta $venta, string $motivo, User $usuario, ?User $autorizo = null): Venta
    {
        $directo = $usuario->can('ventas.cancelar');

        if (! $directo) {
            abort_unless(
                $autorizo && $autorizo->activo && $autorizo->can('ventas.autorizar_cancelacion')
                    && $usuario->can('ventas.cobrar') && $venta->user_id === $usuario->id,
                403,
                'No tienes permiso para cancelar ventas.',
            );
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la cancelación.']);
        }

        if (mb_strlen($motivo) > 255) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es demasiado largo (máximo 255 caracteres).']);
        }

        $autorizo = $directo ? $usuario : $autorizo;

        return DB::transaction(function () use ($venta, $motivo, $usuario, $directo, $autorizo) {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ($venta->estaCancelada()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya estaba cancelada.']);
            }

            if ($venta->devoluciones()->exists()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya tiene devoluciones y no se puede cancelar.']);
            }

            // Una venta de un turno ya cortado no se cancela (cambiaría un corte cerrado): se devuelve completa.
            if ($venta->turno_id) {
                $turno = Turno::query()->lockForUpdate()->find($venta->turno_id);

                if (! $turno?->estaAbierto()) {
                    if (! $directo) {
                        throw ValidationException::withMessages(['venta' => 'El turno de esta venta ya se cerró: pídele a un administrador que la cancele.']);
                    }

                    return $this->cancelarComoDevolucion($venta, $motivo, $usuario, $autorizo);
                }
            }

            // La parte vendida a crédito se descuenta de la deuda del cliente (hasta lo que todavía debe).
            $credito = BigDecimal::of((string) $venta->pagos()->where('forma', 'credito')->sum('monto'));

            if ($venta->cliente_id && $credito->isPositive()) {
                $saldo = BigDecimal::of((string) Cliente::withTrashed()->lockForUpdate()->whereKey($venta->cliente_id)->value('saldo'));
                $aplicar = $credito->isGreaterThan($saldo) ? $saldo : $credito;

                if ($aplicar->isPositive()) {
                    ($this->cuenta)(
                        $venta->cliente_id, 'cancelacion', (string) $aplicar, $usuario, $venta, $venta->turno_id,
                        nota: $aplicar->isLessThan($credito) ? 'El cliente ya había abonado parte de esta venta.' : null,
                    );
                }
            }

            foreach ($venta->partidas->sortBy('articulo_id') as $partida) {
                ($this->movimiento)(
                    $partida->articulo_id,
                    TipoMovimiento::CancelacionVenta,
                    (string) BigDecimal::of((string) $partida->cantidad),
                    (string) $partida->costo_unitario,
                    $usuario,
                    $venta,
                    nota: $motivo,
                );
            }

            $venta->update([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
                'cancelada_por' => $usuario->id,
                'autorizada_por' => $autorizo?->id,
                'motivo_cancelacion' => $motivo,
            ]);

            return $venta->load(['partidas', 'pagos']);
        });
    }

    /** Venta de un turno cerrado: devolución total marcada como cancelación, con quién la autorizó. */
    private function cancelarComoDevolucion(Venta $venta, string $motivo, User $usuario, ?User $autorizo): Venta
    {
        $items = $venta->partidas()->get()->map(fn ($p) => ['partida_id' => $p->id, 'cantidad' => (string) $p->cantidad])->all();

        ($this->devolver)($venta, $items, $motivo, $usuario, esCancelacion: true);

        $venta->update(['autorizada_por' => $autorizo?->id]);

        return $venta->load(['partidas', 'pagos', 'devoluciones']);
    }
}
