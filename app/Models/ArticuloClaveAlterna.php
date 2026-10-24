<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('articulo_claves_alternas')]
#[Fillable(['articulo_id', 'clave'])]
class ArticuloClaveAlterna extends Model
{
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
