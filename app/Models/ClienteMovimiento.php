<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('cliente_movimientos')]
#[Fillable(['cliente_id', 'tipo', 'monto', 'saldo_resultante', 'venta_id', 'turno_id', 'forma', 'referencia', 'nota', 'user_id'])]
class ClienteMovimiento extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'saldo_resultante' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esCargo(): bool
    {
        return $this->tipo === 'cargo';
    }

    public function etiqueta(): string
    {
        return match ($this->tipo) {
            'cargo' => 'Compra a crédito',
            'abono' => 'Abono',
            'cancelacion' => 'Venta cancelada',
            'devolucion' => 'Devolución',
            default => $this->tipo,
        };
    }
}
