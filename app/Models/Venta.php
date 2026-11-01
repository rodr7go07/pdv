<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'serie', 'numero', 'folio', 'caja_id', 'turno_id', 'user_id', 'cliente_id', 'estado', 'lista_precios',
    'subtotal', 'descuento', 'descuento_efectivo', 'impuestos', 'total', 'efectivo_recibido', 'cambio',
    'cancelada_at', 'cancelada_por', 'autorizada_por', 'motivo_cancelacion',
])]
class Venta extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'efectivo_recibido' => 'decimal:2',
            'cambio' => 'decimal:2',
            'descuento_efectivo' => 'boolean',
            'cancelada_at' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por');
    }

    public function impresiones(): HasMany
    {
        return $this->hasMany(Impresion::class)->latest('id');
    }

    public function autorizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizada_por');
    }

    /** La devolución total con la que se canceló esta venta cuando su turno ya había cerrado (si fue así). */
    public function cancelacionTardia(): ?Devolucion
    {
        return $this->devoluciones->firstWhere('es_cancelacion', true);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(VentaPartida::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class)->orderBy('id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }

    /**
     * Impuestos de la venta sumados por nombre (para el ticket): [['nombre' => 'IVA 16%', 'monto' => '6.90'], …].
     * Necesita las partidas cargadas.
     *
     * @return list<array{nombre: string, monto: string}>
     */
    public function desgloseImpuestos(): array
    {
        return $this->partidas
            ->flatMap(fn ($p) => $p->impuestos_detalle ?? [])
            ->groupBy(fn ($i) => $i['nombre'])
            ->map(fn ($grupo, $nombre) => [
                'nombre' => (string) $nombre,
                'monto' => (string) $grupo->reduce(fn (BigDecimal $c, $i) => $c->plus((string) $i['monto']), BigDecimal::zero())->toScale(2, RoundingMode::HalfUp),
            ])->values()->all();
    }

    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }
}
