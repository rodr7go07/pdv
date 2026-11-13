<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'caja_id', 'user_id', 'estado', 'fondo_inicial', 'abierto_at', 'cerrado_at', 'cerrado_por',
    'efectivo_esperado', 'efectivo_contado', 'diferencia', 'notas', 'caja_abierta_id', 'usuario_abierto_id',
])]
class Turno extends Model
{
    protected function casts(): array
    {
        return [
            'fondo_inicial' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'abierto_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class)->latest('created_at')->latest('id');
    }

    public function cortes(): HasMany
    {
        return $this->hasMany(Corte::class)->latest('created_at')->latest('id');
    }

    public function scopeAbiertos(Builder $query): void
    {
        $query->where('estado', 'abierto');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /**
     * Turnos en los que el usuario puede trabajar (decisión 85): el suyo, o el de una caja compartida donde está asignado.
     * El propio va primero.
     */
    public function scopeDisponiblesPara(Builder $query, User $usuario): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('turnos.user_id', $usuario->id)
            ->orWhereHas('caja', fn (Builder $c) => $c->where('compartida', true)
                ->whereHas('usuarios', fn (Builder $u) => $u->whereKey($usuario->id))))
            ->orderByRaw('CASE WHEN turnos.user_id = ? THEN 0 ELSE 1 END', [$usuario->id])
            ->orderBy('turnos.id');
    }

    /** Turno abierto en el que trabaja el usuario: el suyo o el de su caja compartida (como máximo uno). */
    public static function abiertoDe(?User $usuario): ?self
    {
        return $usuario ? self::query()->abiertos()->disponiblesPara($usuario)->with('caja')->first() : null;
    }

    /** ¿El usuario trabaja en este turno? (lo abrió él, o es de una caja compartida donde está asignado) */
    public function esDe(User $usuario): bool
    {
        return $this->user_id === $usuario->id || (bool) $this->caja?->asignado($usuario);
    }

    /** Turno de una caja compartida: lo usan varios y el corte muestra lo de cada vendedor. */
    public function esCompartido(): bool
    {
        return (bool) $this->caja?->compartida;
    }
}
