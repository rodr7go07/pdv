<?php

namespace App\Livewire\Forms;

use App\Enums\MetodoCosteo;
use App\Models\Configuracion;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ConfiguracionForm extends Form
{
    public string $nombre_tienda = '';

    public string $razon_social = '';

    public string $rfc = '';

    public string $telefono = '';

    public string $direccion = '';

    public string $leyenda_ticket = '';

    public string $metodo_costeo = 'promedio';

    public bool $vender_sin_existencia = true;

    public function cargar(Configuracion $configuracion): void
    {
        $this->fill([
            'metodo_costeo' => $configuracion->metodo_costeo?->value ?? 'promedio',
            'vender_sin_existencia' => (bool) $configuracion->vender_sin_existencia,
            'nombre_tienda' => $configuracion->nombre_tienda,
            'razon_social' => $configuracion->razon_social ?? '',
            'rfc' => $configuracion->rfc ?? '',
            'telefono' => $configuracion->telefono ?? '',
            'direccion' => $configuracion->direccion ?? '',
            'leyenda_ticket' => $configuracion->leyenda_ticket ?? '',
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'nombre_tienda' => ['required', 'string', 'max:80'],
            'razon_social' => ['nullable', 'string', 'max:150'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'leyenda_ticket' => ['nullable', 'string', 'max:255'],
            'metodo_costeo' => ['required', Rule::enum(MetodoCosteo::class)],
            'vender_sin_existencia' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'nombre_tienda' => 'nombre de la tienda',
            'razon_social' => 'razón social',
            'rfc' => 'RFC',
            'telefono' => 'teléfono',
            'direccion' => 'dirección',
            'leyenda_ticket' => 'leyenda del ticket',
            'metodo_costeo' => 'método de costeo',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'rfc.regex' => 'El RFC no tiene un formato válido (12 caracteres para empresas, 13 para personas).',
        ];
    }

    /**
     * Datos listos para guardar: los vacíos se guardan como nulos.
     *
     * @return array<string, ?string>
     */
    public function datos(): array
    {
        $datos = $this->validate();

        return collect($datos)
            ->map(fn (mixed $valor) => is_bool($valor) ? $valor : (($valor = trim((string) $valor)) === '' ? null : $valor))
            ->all();
    }
}
