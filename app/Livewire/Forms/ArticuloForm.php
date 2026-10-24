<?php

namespace App\Livewire\Forms;

use App\Models\Articulo;
use Livewire\Form;

class ArticuloForm extends Form
{
    public string $clave = '';

    public string $descripcion = '';

    public string $departamento_id = '';

    public string $unidad_medida_id = '';

    public string $costo = '';

    /** Solo para la pantalla: el margen no se guarda, se deriva del costo y el precio. */
    public string $margen = '';

    public string $precio_1 = '';

    public string $precio_2 = '';

    public string $precio_3 = '';

    /** IVA aplicable (uno solo, o vacío si no lleva). */
    public string $iva_id = '';

    /** @var list<string> */
    public array $ieps = [];

    /** @var list<string> */
    public array $claves_alternas = [];

    public bool $inventariable = true;

    public bool $granel = false;

    public string $existencia_minima = '';

    public string $existencia_maxima = '';

    public bool $activo = true;

    public function cargar(Articulo $articulo): void
    {
        $articulo->loadMissing(['impuestos', 'clavesAlternas']);

        $iva = $articulo->impuestos->first(fn ($i) => $i->tipo->value === 'iva');

        $this->fill([
            'clave' => $articulo->clave,
            'descripcion' => $articulo->descripcion,
            'departamento_id' => (string) $articulo->departamento_id,
            'unidad_medida_id' => (string) $articulo->unidad_medida_id,
            'costo' => $this->recortar((string) $articulo->costo, 4),
            'precio_1' => (string) $articulo->precio_1,
            'precio_2' => (string) ($articulo->precio_2 ?? ''),
            'precio_3' => (string) ($articulo->precio_3 ?? ''),
            'margen' => (string) ($articulo->margen() ?? ''),
            'iva_id' => (string) ($iva?->id ?? ''),
            'ieps' => $articulo->impuestos->filter(fn ($i) => $i->tipo->value === 'ieps')->pluck('id')->map(strval(...))->all(),
            'claves_alternas' => $articulo->clavesAlternas->pluck('clave')->all(),
            'inventariable' => $articulo->inventariable,
            'granel' => $articulo->granel,
            'existencia_minima' => $this->recortar((string) ($articulo->existencia_minima ?? ''), 3),
            'existencia_maxima' => $this->recortar((string) ($articulo->existencia_maxima ?? ''), 3),
            'activo' => $articulo->activo,
        ]);
    }

    /**
     * Los datos tal como los espera la Action GuardarArticulo.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'clave' => $this->clave,
            'descripcion' => $this->descripcion,
            'departamento_id' => $this->departamento_id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'costo' => $this->costo,
            'precio_1' => $this->precio_1,
            'precio_2' => $this->precio_2,
            'precio_3' => $this->precio_3,
            'impuestos' => array_filter([$this->iva_id, ...$this->ieps]),
            'claves_alternas' => $this->claves_alternas,
            'inventariable' => $this->inventariable,
            'granel' => $this->granel,
            'existencia_minima' => $this->inventariable ? $this->existencia_minima : '',
            'existencia_maxima' => $this->inventariable ? $this->existencia_maxima : '',
            'activo' => $this->activo,
        ];
    }

    /** Quita ceros sobrantes: "10.0000" → "10", "12.5000" → "12.5". */
    private function recortar(string $valor, int $decimales): string
    {
        if ($valor === '' || ! str_contains($valor, '.')) {
            return $valor;
        }

        return rtrim(rtrim($valor, '0'), '.');
    }
}
