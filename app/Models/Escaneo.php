<?php

namespace App\Models;

use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['codigo', 'origen', 'medio', 'user_id', 'caja_id', 'articulo_id', 'encontrado'])]
class Escaneo extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'origen' => OrigenEscaneo::class,
            'medio' => MedioEscaneo::class,
            'encontrado' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }
}
