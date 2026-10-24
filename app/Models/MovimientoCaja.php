<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('movimientos_caja')]
#[Fillable(['turno_id', 'tipo', 'monto', 'motivo', 'user_id'])]
class MovimientoCaja extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
