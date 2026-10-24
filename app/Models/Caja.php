<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('cajas')]
#[Fillable(['nombre', 'serie', 'ancho_papel', 'imprimir_automatico', 'activa'])]
class Caja extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'ancho_papel' => 'integer',
            'imprimir_automatico' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }
}
