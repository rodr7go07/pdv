<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('historial_precios')]
#[Fillable(['articulo_id', 'user_id', 'campo', 'valor_anterior', 'valor_nuevo', 'origen'])]
class HistorialPrecio extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'valor_anterior' => 'decimal:4',
            'valor_nuevo' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiquetaCampo(): string
    {
        return match ($this->campo) {
            'costo' => 'Costo',
            'precio_1' => 'Precio 1',
            'precio_2' => 'Precio 2',
            'precio_3' => 'Precio 3',
            default => $this->campo,
        };
    }
}
