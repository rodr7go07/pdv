<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('venta_partidas')]
#[Fillable([
    'venta_id', 'articulo_id', 'clave', 'descripcion', 'unidad', 'cantidad', 'precio_unitario', 'descuento',
    'costo_unitario', 'tasa_impuestos', 'subtotal', 'impuestos', 'total', 'impuestos_detalle',
    'cantidad_devuelta', 'monto_devuelto',
])]
class VentaPartida extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'costo_unitario' => 'decimal:4',
            'tasa_impuestos' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'impuestos_detalle' => 'array',
            'cantidad_devuelta' => 'decimal:3',
            'monto_devuelto' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    /** Cantidad que todavía se puede devolver. */
    public function cantidadDevolvible(): string
    {
        return (string) BigDecimal::of((string) $this->cantidad)->minus((string) $this->cantidad_devuelta)->toScale(3);
    }
}
