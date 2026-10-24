<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ventas_en_espera')]
#[Fillable(['caja_id', 'user_id', 'nombre', 'carrito', 'total'])]
class VentaEnEspera extends Model
{
    protected function casts(): array
    {
        return ['carrito' => 'array', 'total' => 'decimal:2'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
