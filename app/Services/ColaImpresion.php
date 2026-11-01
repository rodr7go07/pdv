<?php

namespace App\Services;

use App\Enums\EstadoImpresion;
use App\Models\Caja;
use App\Models\Impresion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cola de tickets que se piden imprimir en la caja que hizo la venta (por ejemplo desde el teléfono).
 *
 * Quien tiene la impresora es la pantalla de Venta de esa caja: pregunta por los pedidos pendientes, los reserva
 * (pendiente → imprimiendo, de a uno y sin que dos pantallas se pisen) y avisa cuando terminó (impreso). Un pedido
 * que nadie atiende caduca, y uno que se quedó «imprimiendo» (la pantalla se cerró a la mitad) vuelve a la cola
 * hasta agotar sus intentos.
 */
class ColaImpresion
{
    /** Minutos que espera un pedido antes de caducar (si la caja estaba apagada no debe imprimir tickets viejos). */
    public const MINUTOS_VIGENCIA = 10;

    public const MAX_INTENTOS = 3;

    /** Segundos que puede estar un pedido «imprimiendo» antes de darlo por perdido y reintentarlo. */
    public const SEGUNDOS_IMPRIMIENDO = 120;

    /**
     * Pide imprimir el ticket de una venta en su caja. Si ya hay un pedido vigente de esa venta, devuelve ese.
     *
     * @throws ValidationException
     */
    public function solicitar(Venta $venta, User $quien): Impresion
    {
        abort_unless(
            $quien->can('ventas.ver_todas') || ($quien->can('ventas.cobrar') && $venta->user_id === $quien->id),
            403,
            'No puedes imprimir el ticket de esta venta.',
        );

        $caja = Caja::query()->find($venta->caja_id);

        if (! $caja || ! $caja->activa) {
            throw ValidationException::withMessages(['impresion' => 'La caja de esta venta ya no está disponible para imprimir.']);
        }

        return DB::transaction(function () use ($venta, $caja, $quien) {
            $vigente = Impresion::query()->where('venta_id', $venta->id)
                ->whereIn('estado', [EstadoImpresion::Pendiente->value, EstadoImpresion::Imprimiendo->value])->lockForUpdate()->first();

            return $vigente ?? Impresion::query()->create([
                'venta_id' => $venta->id,
                'caja_id' => $caja->id,
                'solicitada_por' => $quien->id,
                'estado' => EstadoImpresion::Pendiente->value,
            ]);
        });
    }

    /**
     * Pedidos que esperan a esta caja (primero los más viejos). Antes limpia los que ya caducaron.
     *
     * @return Collection<int, Impresion>
     */
    public function pendientesDe(Caja $caja): Collection
    {
        $this->caducar($caja);

        return Impresion::query()->with('venta:id,folio')->where('caja_id', $caja->id)
            ->where('estado', EstadoImpresion::Pendiente->value)->orderBy('id')->get();
    }

    /** Reserva el pedido para imprimirlo. Solo una pantalla lo logra: devuelve false si otra se le adelantó. */
    public function reservar(Impresion $impresion): bool
    {
        return Impresion::query()->whereKey($impresion->id)->where('estado', EstadoImpresion::Pendiente->value)
            ->update(['estado' => EstadoImpresion::Imprimiendo->value, 'intentos' => DB::raw('intentos + 1'), 'updated_at' => now()]) === 1;
    }

    /** La pantalla terminó (o no pudo): queda impreso o vuelve a la cola, y tras varios intentos falla. */
    public function confirmar(Impresion $impresion, bool $impreso): void
    {
        $actual = Impresion::query()->whereKey($impresion->id)->where('estado', EstadoImpresion::Imprimiendo->value);

        if ($impreso) {
            $actual->update(['estado' => EstadoImpresion::Impreso->value, 'impreso_at' => now(), 'updated_at' => now()]);

            return;
        }

        $fresco = $actual->first();

        if ($fresco) {
            $fresco->update(['estado' => $fresco->intentos >= self::MAX_INTENTOS ? EstadoImpresion::Fallo->value : EstadoImpresion::Pendiente->value]);
        }
    }

    /** Descarta un pedido que todavía no se imprimió. Devuelve false si ya no estaba vigente. */
    public function cancelar(Impresion $impresion): bool
    {
        return Impresion::query()->whereKey($impresion->id)->whereIn('estado', [EstadoImpresion::Pendiente->value, EstadoImpresion::Imprimiendo->value])
            ->update(['estado' => EstadoImpresion::Cancelado->value, 'updated_at' => now()]) === 1;
    }

    /**
     * Pone al día los pedidos olvidados: los pendientes viejos caducan y los «imprimiendo» perdidos se reintentan.
     * Devuelve cuántos cambió. Sin caja, revisa todas (lo usa el cron).
     */
    public function caducar(?Caja $caja = null): int
    {
        $caja = $caja?->id;

        $caducados = Impresion::query()->when($caja, fn ($q, $id) => $q->where('caja_id', $id))
            ->where('estado', EstadoImpresion::Pendiente->value)->where('created_at', '<', now()->subMinutes(self::MINUTOS_VIGENCIA))
            ->update(['estado' => EstadoImpresion::Caducado->value, 'updated_at' => now()]);

        $perdidos = Impresion::query()->when($caja, fn ($q, $id) => $q->where('caja_id', $id))
            ->where('estado', EstadoImpresion::Imprimiendo->value)->where('updated_at', '<', now()->subSeconds(self::SEGUNDOS_IMPRIMIENDO));

        $reintentables = (clone $perdidos)->where('intentos', '<', self::MAX_INTENTOS)
            ->update(['estado' => EstadoImpresion::Pendiente->value, 'updated_at' => now()]);
        $agotados = $perdidos->where('intentos', '>=', self::MAX_INTENTOS)
            ->update(['estado' => EstadoImpresion::Fallo->value, 'updated_at' => now()]);

        return $caducados + $reintentables + $agotados;
    }
}
