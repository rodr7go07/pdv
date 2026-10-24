<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turno_id', 'tipo', 'user_id', 'datos'])]
class Corte extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['datos' => 'array', 'created_at' => 'datetime'];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiqueta(): string
    {
        return $this->tipo === 'Z' ? 'Corte final (Z)' : 'Corte parcial (X)';
    }
}
