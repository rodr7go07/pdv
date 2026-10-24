<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Table('movimientos_inventario')]
#[Fillable(['articulo_id', 'tipo', 'cantidad', 'costo_unitario', 'existencia_resultante', 'referencia_type', 'referencia_id', 'motivo', 'nota', 'user_id'])]
class MovimientoInventario extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'existencia_resultante' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
