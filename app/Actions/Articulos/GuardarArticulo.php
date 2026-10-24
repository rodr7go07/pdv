<?php

namespace App\Actions\Articulos;

use App\Models\Articulo;
use App\Models\ArticuloClaveAlterna;
use App\Enums\TipoImpuesto;
use App\Models\HistorialPrecio;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea o actualiza un artículo con sus claves alternas, impuestos, imagen
 * e historial de cambios de precio. Todo en una sola transacción.
 *
 * Las claves (principal y alternas) son únicas en todo el sistema,
 * incluso entre artículos eliminados, para no reutilizar un código viejo.
 */
class GuardarArticulo
{
    private const CAMPOS_PRECIO = ['costo', 'precio_1', 'precio_2', 'precio_3'];

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function __invoke(
        ?Articulo $articulo,
        array $datos,
        ?User $usuario = null,
        bool $puedePrecios = true,
        ?UploadedFile $imagen = null,
        bool $quitarImagen = false,
        string $origen = 'manual',
    ): Articulo {
        $datos = $this->normalizar($datos);
        $this->validar($articulo, $datos);

        $imagenNueva = $imagen?->store('articulos', 'public');
        $imagenVieja = $articulo?->imagen_ruta;

        try {
            $guardado = DB::transaction(function () use ($articulo, $datos, $usuario, $puedePrecios, $imagenNueva, $quitarImagen, $origen) {
                if ($articulo?->exists) {
                    $articulo = Articulo::query()->lockForUpdate()->findOrFail($articulo->id);
                }

                $existia = (bool) $articulo;
                $articulo ??= new Articulo;

                $anteriores = $existia ? $articulo->only(self::CAMPOS_PRECIO) : array_fill_keys(self::CAMPOS_PRECIO, null);

                $atributos = collect($datos)->only([
                    'clave', 'descripcion', 'departamento_id', 'unidad_medida_id',
                    'inventariable', 'granel', 'existencia_minima', 'existencia_maxima', 'activo',
                ])->all();

                if ($puedePrecios) {
                    $atributos += collect($datos)->only(self::CAMPOS_PRECIO)->all();
                }

                if ($imagenNueva) {
                    $atributos['imagen_ruta'] = $imagenNueva;
                } elseif ($quitarImagen) {
                    $atributos['imagen_ruta'] = null;
                }

                $articulo->fill($atributos)->save();

                $articulo->impuestos()->sync($datos['impuestos']);
                $this->sincronizarClavesAlternas($articulo, $datos['claves_alternas']);

                if ($puedePrecios) {
                    $this->registrarHistorial($articulo, $anteriores, $existia, $usuario, $origen);
                }

                return $articulo->load(['impuestos', 'clavesAlternas']);
            });
        } catch (QueryException $e) {
            if ($imagenNueva) {
                Storage::disk('public')->delete($imagenNueva);
            }

            // Otra captura simultánea ganó la clave: se informa como error de validación.
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                throw ValidationException::withMessages(['clave' => 'Esa clave acaba de ser usada por otro artículo.']);
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($imagenNueva) {
                Storage::disk('public')->delete($imagenNueva);
            }

            throw $e;
        }

        if ($imagenVieja && $guardado->imagen_ruta !== $imagenVieja) {
            Storage::disk('public')->delete($imagenVieja);
        }

        return $guardado;
    }

    /** @param  array<string, mixed>  $datos */
    private function normalizar(array $datos): array
    {
        $vacioANulo = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $datos['clave'] = trim((string) ($datos['clave'] ?? ''));
        $datos['descripcion'] = trim((string) ($datos['descripcion'] ?? ''));

        foreach (['precio_2', 'precio_3', 'existencia_minima', 'existencia_maxima'] as $campo) {
            $datos[$campo] = $vacioANulo($datos[$campo] ?? null);
        }

        $datos['costo'] = $vacioANulo($datos['costo'] ?? null) ?? '0';
        $datos['precio_1'] = $vacioANulo($datos['precio_1'] ?? null) ?? '0';

        foreach (['inventariable', 'granel', 'activo'] as $campo) {
            $datos[$campo] = (bool) ($datos[$campo] ?? ($campo !== 'granel'));
        }

        $datos['impuestos'] = array_values(array_unique(array_map('intval', $datos['impuestos'] ?? [])));

        $alternas = [];
        foreach ($datos['claves_alternas'] ?? [] as $clave) {
            $clave = trim((string) $clave);
            if ($clave !== '') {
                $alternas[] = $clave;
            }
        }
        $datos['claves_alternas'] = $alternas;

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    private function validar(?Articulo $articulo, array $datos): void
    {
        $id = $articulo?->id;

        $validador = Validator::make($datos, [
            'clave' => [
                'required', 'string', 'max:40',
                Rule::unique('articulos', 'clave')->ignore($id),
                Rule::unique('articulo_claves_alternas', 'clave'),
            ],
            'descripcion' => ['required', 'string', 'max:150'],
            'departamento_id' => ['required', Rule::exists('departamentos', 'id')->whereNull('deleted_at')],
            'unidad_medida_id' => ['required', Rule::exists('unidades_medida', 'id')->whereNull('deleted_at')],
            'costo' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'precio_1' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'precio_2' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'precio_3' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'existencia_minima' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'existencia_maxima' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'gte:existencia_minima'],
            'impuestos' => ['array'],
            'impuestos.*' => [Rule::exists('impuestos', 'id')->whereNull('deleted_at')],
            'claves_alternas' => ['array', 'max:20'],
            'claves_alternas.*' => [
                'string', 'max:40', 'distinct:ignore_case', 'different:clave',
                Rule::unique('articulos', 'clave'),
                Rule::unique('articulo_claves_alternas', 'clave')->ignore($id, 'articulo_id'),
            ],
        ], [
            'clave.unique' => 'Esa clave ya está en uso por otro artículo o clave alterna.',
            'claves_alternas.*.unique' => 'La clave alterna ":input" ya está en uso por otro artículo.',
            'claves_alternas.*.distinct' => 'La clave alterna ":input" está repetida.',
            'claves_alternas.*.different' => 'La clave alterna ":input" no puede ser igual a la clave principal.',
            'existencia_maxima.gte' => 'La existencia máxima no puede ser menor que la mínima.',
        ], [
            'clave' => 'clave',
            'descripcion' => 'descripción',
            'departamento_id' => 'departamento',
            'unidad_medida_id' => 'unidad de medida',
            'precio_1' => 'precio 1',
            'precio_2' => 'precio 2',
            'precio_3' => 'precio 3',
            'existencia_minima' => 'existencia mínima',
            'existencia_maxima' => 'existencia máxima',
        ]);

        $validador->after(function ($v) use ($datos) {
            if (! $v->errors()->has('impuestos.*') && count($datos['impuestos']) > 1) {
                $ivas = Impuesto::query()->whereIn('id', $datos['impuestos'])->where('tipo', TipoImpuesto::Iva->value)->count();

                if ($ivas > 1) {
                    $v->errors()->add('impuestos', 'Un artículo solo puede llevar un IVA (por ejemplo 16% o 0%, no ambos).');
                }
            }

            if ($datos['granel'] && ! $v->errors()->has('unidad_medida_id')) {
                $permiteDecimales = UnidadMedida::query()->whereKey($datos['unidad_medida_id'])->value('permite_decimales');

                if (! $permiteDecimales) {
                    $v->errors()->add('unidad_medida_id', 'Un artículo a granel necesita una unidad que permita decimales (kg, litro, metro…).');
                }
            }
        });

        $validador->validate();
    }

    /** @param  list<string>  $claves */
    private function sincronizarClavesAlternas(Articulo $articulo, array $claves): void
    {
        $actuales = $articulo->clavesAlternas()->get()->keyBy(fn (ArticuloClaveAlterna $c) => mb_strtolower($c->clave));
        $nuevas = collect($claves)->keyBy(fn (string $c) => mb_strtolower($c));

        $articulo->clavesAlternas()
            ->whereIn('id', $actuales->diffKeys($nuevas)->pluck('id'))
            ->delete();

        foreach ($nuevas->diffKeys($actuales) as $clave) {
            $articulo->clavesAlternas()->create(['clave' => $clave]);
        }
    }

    /**
     * @param  array<string, mixed>  $anteriores
     */
    private function registrarHistorial(Articulo $articulo, array $anteriores, bool $existia, ?User $usuario, string $origen): void
    {
        foreach (self::CAMPOS_PRECIO as $campo) {
            $antes = $anteriores[$campo];
            $despues = $articulo->{$campo};

            $cambio = $existia
                ? ! $this->iguales($antes, $despues)
                : ($despues !== null && ! BigDecimal::of((string) $despues)->isZero());

            if (! $cambio) {
                continue;
            }

            HistorialPrecio::query()->create([
                'articulo_id' => $articulo->id,
                'user_id' => $usuario?->id,
                'campo' => $campo,
                'valor_anterior' => $antes,
                'valor_nuevo' => $despues,
                'origen' => $origen,
            ]);
        }
    }

    private function iguales(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return BigDecimal::of((string) $a)->isEqualTo(BigDecimal::of((string) $b));
    }
}
