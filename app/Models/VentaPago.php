<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('venta_pagos')]
#[Fillable(['venta_id', 'forma', 'monto', 'referencia'])]
class VentaPago extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
