<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('cajas')]
#[Fillable(['nombre', 'serie', 'ancho_papel', 'imprimir_automatico', 'impresion_pedidos', 'activa', 'compartida'])]
class Caja extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'ancho_papel' => 'integer',
            'imprimir_automatico' => 'boolean',
            'activa' => 'boolean',
            'compartida' => 'boolean',
        ];
    }

    /** Tickets pedidos desde otro equipo (el teléfono): se imprimen solos o la caja avisa y el cajero toca Imprimir. */
    public const PEDIDOS_SOLA = 'sola';

    public const PEDIDOS_AVISO = 'aviso';

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /** Usuarios que pueden vender en esta caja cuando es compartida (decisión 85). */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'caja_usuario')->withTimestamps();
    }

    /** ¿Este usuario trabaja en esta caja compartida? */
    public function asignado(User $usuario): bool
    {
        return $this->compartida && $this->usuarios()->whereKey($usuario->id)->exists();
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }
}
