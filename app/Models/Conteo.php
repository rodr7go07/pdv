<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'estado', 'user_id', 'aplicado_at'])]
class Conteo extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['aplicado_at' => 'datetime'];
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(ConteoPartida::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function enProceso(): bool
    {
        return $this->estado === 'en_proceso';
    }
}
