<?php

namespace App\Models;

use App\Services\CalculadoraPrecios;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'clave', 'descripcion', 'departamento_id', 'unidad_medida_id',
    'costo', 'precio_1', 'precio_2', 'precio_3',
    'inventariable', 'granel', 'existencia_minima', 'existencia_maxima',
    'imagen_ruta', 'activo',
])]
class Articulo extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:4',
            'precio_1' => 'decimal:2',
            'precio_2' => 'decimal:2',
            'precio_3' => 'decimal:2',
            'existencia' => 'decimal:3',
            'ultimo_costo' => 'decimal:4',
            'costo_promedio' => 'decimal:4',
            'existencia_minima' => 'decimal:3',
            'existencia_maxima' => 'decimal:3',
            'inventariable' => 'boolean',
            'granel' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class)->withTrashed();
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id')->withTrashed();
    }

    public function impuestos(): BelongsToMany
    {
        return $this->belongsToMany(Impuesto::class, 'articulo_impuesto')->withTrashed();
    }

    public function clavesAlternas(): HasMany
    {
        return $this->hasMany(ArticuloClaveAlterna::class);
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(HistorialPrecio::class)->latest('created_at')->latest('id');
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /** Artículos con inventario sin existencia (cero o negativa), tengan o no mínimo configurado. */
    public function scopeAgotados(Builder $query): void
    {
        $query->where('inventariable', true)->where('existencia', '<=', 0);
    }

    /** Lo que se está acabando pero todavía hay: en o por debajo del mínimo, sin contar los agotados. */
    public function scopeBajoMinimoConExistencia(Builder $query): void
    {
        $query->bajoMinimo()->where('existencia', '>', 0);
    }

    /** Artículos con inventario cuya existencia está en o por debajo del mínimo configurado (incluye los agotados que tienen mínimo). */
    public function scopeBajoMinimo(Builder $query): void
    {
        $query->where('inventariable', true)
            ->whereNotNull('existencia_minima')
            ->whereColumn('existencia', '<=', 'existencia_minima');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class)->latest('created_at')->latest('id');
    }

    public function estaBajoMinimo(): bool
    {
        return $this->inventariable
            && $this->existencia_minima !== null
            && (float) $this->existencia <= (float) $this->existencia_minima;
    }

    /** Búsqueda libre por clave, clave alterna o descripción. */
    public function scopeBuscar(Builder $query, ?string $texto): void
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return;
        }

        $patron = '%'.addcslashes($texto, '%_\\').'%';

        $query->where(function (Builder $q) use ($patron) {
            $q->where('articulos.clave', 'like', $patron)
                ->orWhere('articulos.descripcion', 'like', $patron)
                ->orWhereHas('clavesAlternas', fn (Builder $a) => $a->where('clave', 'like', $patron));
        });
    }

    /** Coincidencia exacta con la clave principal o una alterna (lo que envía el escáner). */
    public static function porClave(string $clave): ?self
    {
        $clave = trim($clave);

        if ($clave === '') {
            return null;
        }

        return self::query()->where('clave', $clave)->first()
            ?? self::query()->whereHas('clavesAlternas', fn (Builder $q) => $q->where('clave', $clave))->first();
    }

    /** Suma de las tasas de los impuestos del artículo (porcentaje). */
    public function tasaImpuestos(): string
    {
        return CalculadoraPrecios::sumarTasas(
            $this->impuestos->map(fn (Impuesto $i) => $i->tasaAplicable())->all(),
        );
    }

    public function precioSinImpuestos(string $lista = 'precio_1'): string
    {
        return CalculadoraPrecios::precioSinImpuestos((string) $this->{$lista}, $this->tasaImpuestos());
    }

    /** Margen de utilidad sobre el costo, en porcentaje. Nulo si no hay costo. */
    public function margen(): ?string
    {
        return CalculadoraPrecios::margenDesdePrecio((string) $this->costo, (string) $this->precio_1, $this->tasaImpuestos());
    }

    public function imagenUrl(): ?string
    {
        return $this->imagen_ruta ? Storage::disk('public')->url($this->imagen_ruta) : null;
    }
}
