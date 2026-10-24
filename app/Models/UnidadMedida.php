<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('unidades_medida')]
#[Fillable(['nombre', 'abreviatura', 'permite_decimales', 'activo'])]
class UnidadMedida extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'permite_decimales' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true);
    }
}
