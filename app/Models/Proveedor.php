<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('proveedores')]
#[Fillable(['nombre', 'contacto', 'telefono', 'correo', 'rfc', 'notas', 'activo'])]
class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
