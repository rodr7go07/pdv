<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('proveedores')]
#[Fillable(['nombre', 'contacto', 'telefono', 'correo', 'rfc', 'notas', 'dias_visita', 'activo'])]
class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'dias_visita' => 'array'];
    }

    /** Nombres cortos de los días de la semana (1 = lunes … 7 = domingo). */
    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }

    /** «Lun, Jue» o null si no tiene días de visita. */
    public function visitaTexto(): ?string
    {
        $dias = collect($this->dias_visita ?? [])->map(fn ($d) => self::DIAS[(int) $d] ?? null)->filter();

        return $dias->isEmpty() ? null : $dias->implode(', ');
    }

    /**
     * Días que hay que cubrir si hoy se arma el pedido: hasta la visita siguiente a la próxima.
     * La mercancía que llega en la próxima visita (hoy mismo si hoy pasa) tiene que alcanzar hasta la que sigue,
     * y lo que hay en tienda tiene que alcanzar hasta esa primera visita. Null si no tiene días de visita.
     */
    public function diasHastaSiguienteVisita(?CarbonInterface $hoy = null): ?int
    {
        $dias = collect($this->dias_visita ?? [])->map(fn ($d) => (int) $d)->filter(fn (int $d) => $d >= 1 && $d <= 7)->unique()->sort()->values();

        if ($dias->isEmpty()) {
            return null;
        }

        $hoy = CarbonImmutable::instance($hoy ?? now())->startOfDay();
        $visitas = collect(range(0, 14))->map(fn (int $i) => $hoy->addDays($i))->filter(fn ($f) => $dias->contains($f->dayOfWeekIso))->values();

        // Con un solo día a la semana la siguiente visita a la próxima es 7 días después.
        return (int) $hoy->diffInDays($visitas[1]);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
