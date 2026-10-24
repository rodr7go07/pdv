<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'telefono', 'correo', 'rfc', 'direccion', 'lista_precios', 'limite_credito', 'notas', 'activo'])]
class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'lista_precios' => 'integer',
            'limite_credito' => 'decimal:2',
            'saldo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ClienteMovimiento::class)->latest('created_at')->latest('id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    public function scopeConSaldo(Builder $query): void
    {
        $query->where('saldo', '>', 0);
    }

    public function tieneCredito(): bool
    {
        return BigDecimal::of((string) ($this->limite_credito ?? 0))->isPositive();
    }

    /** Cuánto crédito le queda (límite − saldo), nunca negativo. */
    public function disponible(): string
    {
        $d = BigDecimal::of((string) ($this->limite_credito ?? 0))->minus((string) ($this->saldo ?? 0));

        return (string) ($d->isNegative() ? BigDecimal::zero()->toScale(2) : $d->toScale(2));
    }
}
