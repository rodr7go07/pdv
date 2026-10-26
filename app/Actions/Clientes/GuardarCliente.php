<?php

namespace App\Actions\Clientes;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta y edición de clientes. Lista de precios y límite de crédito son decisiones del dueño:
 * solo se guardan si quien lo hace tiene el permiso clientes.credito (si no, se conservan).
 */
class GuardarCliente
{
    /**
     * @param  array{nombre?: ?string, telefono?: ?string, correo?: ?string, rfc?: ?string, direccion?: ?string, lista_precios?: int|string|null, limite_credito?: int|string|null, notas?: ?string, activo?: bool}  $datos
     *
     * @throws ValidationException
     */
    public function __invoke(?Cliente $cliente, array $datos, User $quienLoHace): Cliente
    {
        abort_unless($quienLoHace->can('clientes.administrar'), 403, 'No tienes permiso para administrar clientes.');

        $conCredito = $quienLoHace->can('clientes.credito');

        $validado = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:120'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'lista_precios' => [$conCredito ? 'required' : 'nullable', Rule::in(['1', '2', '3'])],
            'limite_credito' => [$conCredito ? 'required' : 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ], ['rfc.regex' => 'El RFC no tiene un formato válido.'], [
            'rfc' => 'RFC', 'correo' => 'correo', 'lista_precios' => 'lista de precios', 'limite_credito' => 'límite de crédito',
        ])->validate();

        $campos = [
            'nombre' => trim($validado['nombre']),
            'telefono' => self::textoONulo($validado['telefono'] ?? null),
            'correo' => self::textoONulo($validado['correo'] ?? null),
            'rfc' => ($rfc = self::textoONulo($validado['rfc'] ?? null)) ? mb_strtoupper($rfc) : null,
            'direccion' => self::textoONulo($validado['direccion'] ?? null),
            'notas' => self::textoONulo($validado['notas'] ?? null),
            'activo' => (bool) ($validado['activo'] ?? true),
        ];

        if ($conCredito) {
            $campos['lista_precios'] = (int) $validado['lista_precios'];
            $campos['limite_credito'] = $validado['limite_credito'];
        }

        $cliente ??= new Cliente;
        $cliente->fill($campos)->save();

        return $cliente;
    }

    private static function textoONulo(?string $valor): ?string
    {
        return ($valor = trim((string) $valor)) === '' ? null : $valor;
    }
}
