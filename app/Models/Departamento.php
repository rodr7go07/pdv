<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'activo'])]
class Departamento extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
