<?php

namespace App\Actions\Inventario;

use App\Enums\MotivoAjuste;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Services\CalculadoraPrecios;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Ajuste manual de existencia con motivo obligatorio (merma, caducidad, robo, corrección…). */
class AjustarInventario
{
    public function __construct(private readonly RegistrarMovimientoInventario $registrar) {}

    /**
     * @param  'entrada'|'salida'  $direccion
     *
     * @throws ValidationException
     */
    public function __invoke(Articulo $articulo, string $direccion, string $cantidad, MotivoAjuste $motivo, ?string $nota, User $usuario): MovimientoInventario
    {
        $datos = Validator::make(
            ['direccion' => $direccion, 'cantidad' => $cantidad, 'nota' => $nota],
            [
                'direccion' => ['required', Rule::in(['entrada', 'salida'])],
                'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
                'nota' => [Rule::requiredIf($motivo === MotivoAjuste::Otro), 'nullable', 'string', 'max:255'],
            ],
            ['nota.required' => 'Explica el motivo en la nota.', 'cantidad.gt' => 'La cantidad debe ser mayor que cero.'],
            ['cantidad' => 'cantidad', 'nota' => 'nota'],
        )->after(function ($v) use ($articulo, $direccion, $cantidad, $motivo) {
            if ($motivo->soloSalida() && $direccion === 'entrada') {
                $v->errors()->add('direccion', 'Una merma, caducidad o robo solo puede ser una salida.');
            }

            if (! $articulo->inventariable) {
                $v->errors()->add('articulo', 'Este artículo no lleva control de inventario.');
            }

            if (! $articulo->unidad->permite_decimales && is_numeric($cantidad) && CalculadoraPrecios::tieneDecimales($cantidad)) {
                $v->errors()->add('cantidad', 'La unidad de este artículo solo admite cantidades enteras.');
            }
        })->validate();

        $firmada = $direccion === 'salida' ? (string) BigDecimal::of($datos['cantidad'])->negated() : (string) BigDecimal::of($datos['cantidad']);

        return ($this->registrar)(
            $articulo,
            TipoMovimiento::Ajuste,
            $firmada,
            usuario: $usuario,
            motivo: $motivo->value,
            nota: $nota ? trim($nota) : null,
        );
    }
}
