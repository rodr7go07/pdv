<?php

namespace App\Actions\Proveedores;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Alta y edición de proveedores (permiso proveedores.administrar). */
class GuardarProveedor
{
    /**
     * @param  array{nombre?: ?string, contacto?: ?string, telefono?: ?string, correo?: ?string, rfc?: ?string, notas?: ?string, activo?: bool}  $datos
     *
     * @throws ValidationException
     */
    public function __invoke(?Proveedor $proveedor, array $datos, User $quienLoHace): Proveedor
    {
        abort_unless($quienLoHace->can('proveedores.administrar'), 403, 'No tienes permiso para administrar proveedores.');

        $validado = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('proveedores', 'nombre')->ignore($proveedor?->id)],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:120'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ], [
            'nombre.unique' => 'Ya existe un proveedor con ese nombre.',
            'rfc.regex' => 'El RFC no tiene un formato válido.',
        ], ['rfc' => 'RFC', 'correo' => 'correo'])->validate();

        $proveedor ??= new Proveedor;
        $proveedor->fill([
            'nombre' => trim($validado['nombre']),
            'contacto' => self::textoONulo($validado['contacto'] ?? null),
            'telefono' => self::textoONulo($validado['telefono'] ?? null),
            'correo' => self::textoONulo($validado['correo'] ?? null),
            'rfc' => ($rfc = self::textoONulo($validado['rfc'] ?? null)) ? mb_strtoupper($rfc) : null,
            'notas' => self::textoONulo($validado['notas'] ?? null),
            'activo' => (bool) ($validado['activo'] ?? true),
        ])->save();

        return $proveedor;
    }

    private static function textoONulo(?string $valor): ?string
    {
        return ($valor = trim((string) $valor)) === '' ? null : $valor;
    }
}
