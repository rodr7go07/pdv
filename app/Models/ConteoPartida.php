<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('conteo_partidas')]
#[Fillable(['conteo_id', 'articulo_id', 'existencia_sistema', 'cantidad_contada'])]
class ConteoPartida extends Model
{
    protected function casts(): array
    {
        return [
            'existencia_sistema' => 'decimal:3',
            'cantidad_contada' => 'decimal:3',
        ];
    }

    public function conteo(): BelongsTo
    {
        return $this->belongsTo(Conteo::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    /** Diferencia entre lo contado y lo que decía el sistema (negativa = faltante). */
    public function diferencia(): string
    {
        return (string) BigDecimal::of((string) $this->cantidad_contada)
            ->minus((string) $this->existencia_sistema)
            ->toScale(3);
    }
}
