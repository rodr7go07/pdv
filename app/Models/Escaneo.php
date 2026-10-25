<?php

namespace App\Models;

use App\Enums\MedioEscaneo;
use App\Enums\OrigenEscaneo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /**
     * Códigos que hoy no pertenecen a ningún artículo: no se encontró uno al escanear y tampoco se dio de alta después
     * (como clave principal o alterna). Un código escaneado antes de crear el artículo deja de contar como pendiente.
     */
    public function scopeSinArticulo(Builder $consulta): void
    {
        // `encontrado = false`: si se encontró un artículo al escanear y luego se borró de verdad, no es un código pendiente.
        $consulta->where('encontrado', false)
            ->whereNull('articulo_id')
            ->whereNotExists(fn ($q) => $q->from('articulos')->whereColumn('articulos.clave', 'escaneos.codigo')->whereNull('articulos.deleted_at'))
            ->whereNotExists(fn ($q) => $q->from('articulo_claves_alternas as ca')
                ->join('articulos as a', 'a.id', '=', 'ca.articulo_id')
                ->whereColumn('ca.clave', 'escaneos.codigo')->whereNull('a.deleted_at'));
    }

    /** Artículo actual de cada código (clave principal o alterna): [código => Articulo]. */
    public static function articulosActuales(Collection $codigos): Collection
    {
        $codigos = $codigos->filter()->unique()->values();

        if ($codigos->isEmpty()) {
            return collect();
        }

        // Las claves se indexan en minúsculas: MySQL compara sin distinguir mayúsculas (utf8mb4_unicode_ci) y PHP sí.
        $porClave = Articulo::query()->whereIn('clave', $codigos)->get(['id', 'clave', 'descripcion'])
            ->mapWithKeys(fn ($articulo) => [mb_strtolower($articulo->clave) => $articulo]);
        $porAlterna = ArticuloClaveAlterna::query()->whereIn('clave', $codigos)->with('articulo:id,clave,descripcion')->get()
            ->filter(fn ($alterna) => $alterna->articulo)->mapWithKeys(fn ($alterna) => [mb_strtolower($alterna->clave) => $alterna->articulo]);

        // union (y no merge): merge renumera las claves que son solo dígitos, como los códigos de barras.
        return $porClave->union($porAlterna);
    }
}
