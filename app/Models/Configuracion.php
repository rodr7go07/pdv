<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[Table('configuracion')]
#[Fillable(['nombre_tienda', 'razon_social', 'rfc', 'direccion', 'telefono', 'leyenda_ticket', 'logo_ruta'])]
class Configuracion extends Model
{
    private const CACHE_KEY = 'configuracion.tienda';

    /**
     * Registro único de configuración (se crea con valores por defecto si no existe).
     */
    public static function actual(): self
    {
        // Se cachean los atributos (no el modelo): Laravel 13 no deserializa objetos desde la caché.
        $atributos = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => self::query()->firstOrCreate([], ['nombre_tienda' => 'Mi tienda'])->getAttributes(),
        );

        return (new self)->newFromBuilder($atributos);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_ruta ? Storage::disk('public')->url($this->logo_ruta) : null;
    }
}
