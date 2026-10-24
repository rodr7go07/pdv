<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'serie', 'numero', 'folio', 'caja_id', 'turno_id', 'user_id', 'cliente_id', 'estado', 'lista_precios',
    'subtotal', 'descuento', 'impuestos', 'total', 'efectivo_recibido', 'cambio',
    'cancelada_at', 'cancelada_por', 'motivo_cancelacion',
])]
class Venta extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'efectivo_recibido' => 'decimal:2',
            'cambio' => 'decimal:2',
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

    public function scopeCompletadas(Builder $query): void
    {
        $query->where('estado', 'completada');
    }

    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /** Total devuelto (suma de las devoluciones). */
    public function montoDevuelto(): string
    {
        return (string) $this->partidas->sum('monto_devuelto');
    }
}
