<?php

namespace App\Actions\Inventario;

use App\Actions\Compras\RegistrarCompra;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Entrada rápida de mercancía (decisión 84): sube la existencia sin capturar proveedor, factura ni costo.
 *
 * Se guarda como una compra de tipo «entrada» para que quede en Compras, en el kardex y en los reportes como lo que
 * es (mercancía que llegó), no como una corrección. El costo de cada renglón es el costo vigente del artículo, así
 * que el costo promedio y el último costo no se mueven; si se escribe otro costo, se usa ese como en una compra.
 */
class RegistrarEntradaRapida
{
    public function __construct(private readonly RegistrarCompra $registrarCompra) {}

    /**
     * @param  list<array{articulo_id: int|string, cantidad: string, costo?: ?string}>  $lineas
     *
     * @throws ValidationException
     */
    public function __invoke(array $lineas, User $usuario, ?int $proveedorId = null, ?string $notas = null): Compra
    {
        abort_unless($usuario->can('compras.registrar'), 403, 'No tienes permiso para meter mercancía.');

        $costos = Articulo::query()->whereIn('id', collect($lineas)->pluck('articulo_id')->map(fn ($id) => (int) $id))
            ->pluck('costo', 'id');

        // Solo quien puede cambiar costos escribe otro costo; para los demás se usa el vigente.
        $puedeCosto = $usuario->can('articulos.editar_precio');

        $partidas = array_map(fn (array $l) => [
            'articulo_id' => $l['articulo_id'],
            'cantidad' => trim((string) $l['cantidad']),
            'costo_unitario' => $puedeCosto && is_numeric($l['costo'] ?? null)
                ? trim((string) $l['costo'])
                : (string) ($costos[(int) $l['articulo_id']] ?? '0'),
        ], $lineas);

        return ($this->registrarCompra)([
            'tipo' => 'entrada',
            'proveedor_id' => $proveedorId,
            'fecha' => now()->toDateString(),
            'notas' => $notas !== null && trim($notas) !== '' ? trim($notas) : 'Entrada rápida',
        ], $partidas, $usuario);
    }
}
