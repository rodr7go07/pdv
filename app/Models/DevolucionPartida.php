<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('devolucion_partidas')]
#[Fillable(['devolucion_id', 'venta_partida_id', 'articulo_id', 'cantidad', 'monto'])]
class DevolucionPartida extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'monto' => 'decimal:2'];
    }

    public function partidaVenta(): BelongsTo
    {
        return $this->belongsTo(VentaPartida::class, 'venta_partida_id');
    }
}
