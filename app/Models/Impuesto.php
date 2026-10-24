<?php

namespace App\Models;

use App\Enums\TipoImpuesto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'tipo', 'tasa', 'exento', 'activo'])]
class Impuesto extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'tipo' => TipoImpuesto::class,
            'tasa' => 'decimal:4',
            'exento' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'articulo_impuesto');
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /** Tasa que realmente se cobra: un impuesto exento no suma. */
    public function tasaAplicable(): string
    {
        return $this->exento ? '0' : (string) $this->tasa;
    }
}
