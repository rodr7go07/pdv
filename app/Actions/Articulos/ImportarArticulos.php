<?php

namespace App\Actions\Articulos;

use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Importa artículos desde las filas de una hoja de Excel.
 *
 * Es de todo o nada: si una fila tiene errores no se guarda ninguna, para que
 * el archivo corregido se pueda volver a subir sin duplicar nada.
 * Si la clave ya existe se actualiza ese artículo; las celdas vacías conservan
 * el valor actual. Los departamentos y proveedores que no existen se crean.
 * La columna `existencia` solo se usa en artículos nuevos (la existencia de uno que ya existe solo cambia con
 * movimientos de inventario) y una existencia negativa se carga como cero.
 */
class ImportarArticulos
{
    private int $negativas = 0;

    private int $existenciaOmitida = 0;

    private bool $puedeExistencia = true;

    public function __construct(private readonly GuardarArticulo $guardar) {}

    /**
     * @param  list<array<string, mixed>>  $filas  filas con encabezados normalizados (clave, descripcion, …)
     * @return array{creados: int, actualizados: int, errores: list<array{fila: int, clave: string, mensajes: list<string>}>, sin_permiso_precios: bool, avisos: list<string>}
     */
    public function __invoke(array $filas, User $usuario, bool $puedePrecios): array
    {
        $catalogos = $this->cargarCatalogos();
        $errores = [];
        $creados = 0;
        $actualizados = 0;
        $vistas = [];
        $this->negativas = 0;
        $this->existenciaOmitida = 0;
        $this->puedeExistencia = $usuario->can('inventario.ajustar'); // meter existencia es un movimiento de inventario

        DB::beginTransaction();

        try {
            foreach ($filas as $indice => $fila) {
                $numero = $indice + 2; // fila 1 = encabezados

                if ($this->filaVacia($fila)) {
                    continue;
                }

                $clave = trim((string) ($fila['clave'] ?? ''));

                // Una clave no puede aparecer dos veces en el mismo archivo.
                if ($clave !== '') {
                    $llave = Str::lower($clave);

                    if (isset($vistas[$llave])) {
                        $errores[] = ['fila' => $numero, 'clave' => $clave, 'mensajes' => ["La clave está repetida: ya aparece en la fila {$vistas[$llave]}."]];

                        continue;
                    }

                    $vistas[$llave] = $numero;
                }

                try {
                    $existente = $clave === '' ? null : Articulo::query()->with(['impuestos', 'clavesAlternas'])->where('clave', $clave)->first();
                    $datos = $this->armarDatos($fila, $existente, $catalogos);

                    ($this->guardar)($existente, $datos, $usuario, $puedePrecios, origen: 'importacion');

                    $existente ? $actualizados++ : $creados++;
                } catch (ValidationException $e) {
                    $errores[] = ['fila' => $numero, 'clave' => $clave, 'mensajes' => array_values(array_unique(array_merge(...array_values($e->errors()))))];
                } catch (\InvalidArgumentException $e) {
                    $errores[] = ['fila' => $numero, 'clave' => $clave, 'mensajes' => [$e->getMessage()]];
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($errores !== []) {
            DB::rollBack();

            return ['creados' => 0, 'actualizados' => 0, 'errores' => $errores, 'sin_permiso_precios' => false, 'avisos' => []];
        }

        DB::commit();

        $avisos = [];

        if ($this->negativas > 0) {
            $avisos[] = $this->negativas.' '.($this->negativas === 1 ? 'artículo traía existencia negativa: se cargó' : 'artículos traían existencia negativa: se cargaron').' sin existencia.';
        }

        if ($this->existenciaOmitida > 0) {
            $avisos[] = 'La existencia de '.$this->existenciaOmitida.' '.($this->existenciaOmitida === 1 ? 'artículo' : 'artículos').' no se cargó (si ya existían, o si no tienes permiso para capturar existencia): se ajusta desde Inventario.';
        }

        return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => [], 'sin_permiso_precios' => ! $puedePrecios, 'avisos' => $avisos];
    }

    /** @return array{departamentos: array<string, Departamento>, unidades: array<string, UnidadMedida>, impuestos: array<string, Impuesto>, proveedores: array<string, Proveedor>} */
    private function cargarCatalogos(): array
    {
        $unidades = [];
        foreach (UnidadMedida::query()->get() as $u) {
            $unidades[$this->clave($u->nombre)] = $u;
            $unidades[$this->clave($u->abreviatura)] = $u;
        }

        return [
            'departamentos' => Departamento::query()->get()->keyBy(fn ($d) => $this->clave($d->nombre))->all(),
            'unidades' => $unidades,
            'impuestos' => Impuesto::query()->get()->keyBy(fn ($i) => $this->clave($i->nombre))->all(),
            'proveedores' => Proveedor::withTrashed()->get()->keyBy(fn ($p) => $this->clave($p->nombre))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, array<string, mixed>>  $catalogos
     * @return array<string, mixed>
     */
    private function armarDatos(array $fila, ?Articulo $existente, array &$catalogos): array
    {
        $base = $existente ? $this->datosActuales($existente) : [
            'inventariable' => true, 'granel' => false, 'activo' => true,
            'impuestos' => [], 'claves_alternas' => [],
            'costo' => '0', 'precio_1' => '0',
        ];

        $datos = $base;
        $texto = fn (string $campo) => trim((string) ($fila[$campo] ?? ''));

        $datos['clave'] = $texto('clave');

        foreach (['descripcion'] as $campo) {
            if ($texto($campo) !== '') {
                $datos[$campo] = $texto($campo);
            }
        }

        // Departamento: se crea si no existe.
        if (($nombre = $texto('departamento')) !== '') {
            $datos['departamento_id'] = ($catalogos['departamentos'][$this->clave($nombre)] ??= Departamento::query()->create(['nombre' => $nombre]))->id;
        } elseif (! $existente) {
            throw new \InvalidArgumentException('Falta el departamento.');
        }

        // Proveedor principal: se crea si no existe. Sin dato, el artículo conserva el que tiene.
        if (($nombre = $texto('proveedor')) !== '') {
            $proveedor = $catalogos['proveedores'][$this->clave($nombre)] ??= Proveedor::query()->create(['nombre' => $nombre, 'activo' => true]);
            $proveedor->trashed() && $proveedor->restore();
            $datos['proveedor_id'] = $proveedor->id;
        }

        // Unidad: por nombre o abreviatura; debe existir.
        if (($nombre = $texto('unidad')) !== '') {
            $unidad = $catalogos['unidades'][$this->clave($nombre)] ?? null;
            $unidad ?: throw new \InvalidArgumentException("La unidad «{$nombre}» no existe. Revisa la hoja «Catálogos».");
            $datos['unidad_medida_id'] = $unidad->id;
        } elseif (! $existente) {
            throw new \InvalidArgumentException('Falta la unidad de medida.');
        }

        foreach (['costo', 'precio_1', 'precio_2', 'precio_3', 'existencia_minima', 'existencia_maxima'] as $campo) {
            if (array_key_exists($campo, $fila) && $texto($campo) !== '') {
                $datos[$campo] = $this->numero($texto($campo), $campo);
            }
        }

        foreach (['inventariable', 'granel', 'activo'] as $campo) {
            if ($texto($campo) !== '') {
                $datos[$campo] = $this->booleano($texto($campo), $campo);
            }
        }

        if ($texto('impuestos') !== '') {
            $datos['impuestos'] = [];
            foreach (preg_split('/[,;+|]/', $texto('impuestos')) as $nombre) {
                if (trim($nombre) === '') {
                    continue;
                }
                $impuesto = $catalogos['impuestos'][$this->clave($nombre)] ?? null;
                $impuesto ?: throw new \InvalidArgumentException('El impuesto «'.trim($nombre).'» no existe. Revisa la hoja «Catálogos».');
                $datos['impuestos'][] = $impuesto->id;
            }
        }

        // Existencia con la que se da de alta el artículo (queda en su historial como «Existencia inicial»).
        if ($texto('existencia') !== '') {
            $cantidad = $this->numero($texto('existencia'), 'existencia');

            if ($existente || ! $this->puedeExistencia) {
                $this->existenciaOmitida++;
            } elseif (BigDecimal::of($cantidad)->isNegative()) {
                $this->negativas++;
            } else {
                $datos['existencia_inicial'] = $cantidad;
            }
        }

        if ($texto('claves_alternas') !== '') {
            $datos['claves_alternas'] = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $texto('claves_alternas')))));
        }

        return $datos;
    }

    /** @return array<string, mixed> */
    private function datosActuales(Articulo $a): array
    {
        return [
            'clave' => $a->clave,
            'descripcion' => $a->descripcion,
            'departamento_id' => $a->departamento_id,
            'unidad_medida_id' => $a->unidad_medida_id,
            'costo' => (string) $a->costo,
            'precio_1' => (string) $a->precio_1,
            'precio_2' => $a->precio_2,
            'precio_3' => $a->precio_3,
            'inventariable' => $a->inventariable,
            'granel' => $a->granel,
            'existencia_minima' => $a->existencia_minima,
            'existencia_maxima' => $a->existencia_maxima,
            'activo' => $a->activo,
            'impuestos' => $a->impuestos->pluck('id')->all(),
            'claves_alternas' => $a->clavesAlternas->pluck('clave')->all(),
        ];
    }

    private function numero(string $valor, string $campo): string
    {
        $limpio = str_replace(['$', ',', ' '], '', $valor);

        if (! is_numeric($limpio)) {
            throw new \InvalidArgumentException("«{$valor}» no es un número válido en «{$campo}».");
        }

        return $limpio;
    }

    private function booleano(string $valor, string $campo): bool
    {
        return match (Str::lower(Str::ascii($valor))) {
            'si', 's', '1', 'true', 'x', 'verdadero' => true,
            'no', 'n', '0', 'false', 'falso' => false,
            default => throw new \InvalidArgumentException("«{$valor}» no es válido en «{$campo}». Usa SI o NO."),
        };
    }

    /** @param  array<string, mixed>  $fila */
    private function filaVacia(array $fila): bool
    {
        return collect($fila)->every(fn ($v) => trim((string) $v) === '');
    }

    /** Llave de comparación: sin acentos, minúsculas, sin símbolos repetidos. */
    private function clave(string $texto): string
    {
        return preg_replace('/\s+/', ' ', trim(Str::lower(Str::ascii(str_replace('%', '', $texto)))));
    }
}
