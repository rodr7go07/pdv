<?php

namespace App\Models;

use App\Enums\EstadoImpresion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('impresiones')]
#[Fillable(['venta_id', 'caja_id', 'solicitada_por', 'estado', 'intentos', 'impreso_at'])]
class Impresion extends Model
{
    protected function casts(): array
    {
        return ['estado' => EstadoImpresion::class, 'impreso_at' => 'datetime', 'intentos' => 'integer'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class)->withTrashed();
    }

    public function solicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }
}
