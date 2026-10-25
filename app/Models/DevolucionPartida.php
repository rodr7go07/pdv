<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('devolucion_partidas')]
#[Fillable(['devolucion_id', 'venta_partida_id', 'articulo_id', 'cantidad', 'monto'])]
class DevolucionPartida extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'monto' => 'decimal:2'];
    }
}
