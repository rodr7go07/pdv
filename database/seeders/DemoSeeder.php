<?php

namespace Database\Seeders;

use App\Actions\Articulos\GuardarArticulo;
use App\Actions\Compras\RegistrarCompra;
use App\Models\Articulo;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para probar el sistema (artículos, proveedor y una compra con existencias).
 * Solo corre en entornos local y testing: `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder solo se ejecuta en entornos local o testing.');

            return;
        }

        $this->call([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class, CajasSeeder::class]);

        if (Articulo::query()->exists()) {
            $this->command?->info('Ya hay artículos: no se agregan datos de demostración.');

            return;
        }

        $usuario = User::query()->where('usuario', 'admin')->first() ?? User::factory()->create();
        $guardar = app(GuardarArticulo::class);

        $iva16 = Impuesto::query()->where('nombre', 'IVA 16%')->value('id');
        $iva0 = Impuesto::query()->where('nombre', 'IVA 0%')->value('id');
        $ieps = Impuesto::query()->where('nombre', 'IEPS 8%')->value('id');
        $unidad = fn (string $abrev) => UnidadMedida::query()->where('abreviatura', $abrev)->value('id');

        $departamentos = collect(['Abarrotes', 'Lácteos y huevo', 'Bebidas', 'Frutas y verduras', 'Panadería', 'Botanas y dulces', 'Limpieza'])
            ->mapWithKeys(fn ($n) => [$n => Departamento::query()->firstOrCreate(['nombre' => $n])->id]);

        // [clave, descripción, departamento, unidad, costo, precio, impuestos, granel, mínimo, alternas]
        $catalogo = [
            ['7501055300075', 'REFRESCO COLA 600 ML', 'Bebidas', 'pza', 11.20, 18.00, [$iva16], false, 24, ['COLA600']],
            ['7501055363057', 'REFRESCO COLA 2 L', 'Bebidas', 'pza', 24.50, 35.00, [$iva16], false, 12, []],
            ['7501031311309', 'AGUA PURIFICADA 1 L', 'Bebidas', 'pza', 6.00, 11.00, [$iva0], false, 24, []],
            ['7501020540970', 'JUGO DE NARANJA 1 L', 'Bebidas', 'pza', 21.00, 32.00, [$iva16], false, 6, []],
            ['7501000111206', 'LECHE ENTERA 1 L', 'Lácteos y huevo', 'pza', 20.50, 27.00, [$iva0], false, 24, ['LECHE1L']],
            ['7501000112012', 'LECHE DESLACTOSADA 1 L', 'Lácteos y huevo', 'pza', 22.00, 29.00, [$iva0], false, 12, []],
            ['7501020512281', 'YOGUR FRESA 1 KG', 'Lácteos y huevo', 'pza', 31.00, 44.00, [$iva0], false, 6, []],
            ['HUEVO-KG', 'HUEVO BLANCO', 'Lácteos y huevo', 'kg', 36.00, 48.00, [$iva0], true, 10, []],
            ['QUESO-OAX', 'QUESO OAXACA', 'Lácteos y huevo', 'kg', 125.00, 170.00, [$iva0], true, 3, []],
            ['7501000670503', 'ARROZ SUPER EXTRA 1 KG', 'Abarrotes', 'pza', 24.00, 34.00, [$iva0], false, 12, []],
            ['7501003324202', 'FRIJOL NEGRO 900 G', 'Abarrotes', 'pza', 27.00, 38.50, [$iva0], false, 12, []],
            ['7501000100019', 'ACEITE VEGETAL 1 L', 'Abarrotes', 'pza', 38.00, 52.00, [$iva16], false, 12, []],
            ['7501013111107', 'AZÚCAR ESTÁNDAR 1 KG', 'Abarrotes', 'pza', 26.00, 36.00, [$iva0], false, 12, []],
            ['7501052470079', 'SAL DE MESA 1 KG', 'Abarrotes', 'pza', 9.50, 15.00, [$iva0], false, 6, []],
            ['7501008610018', 'PASTA SPAGHETTI 200 G', 'Abarrotes', 'pza', 6.50, 11.00, [$iva0], false, 24, []],
            ['7501035900019', 'ATÚN EN AGUA 140 G', 'Abarrotes', 'pza', 15.50, 23.00, [$iva16], false, 12, []],
            ['MANZANA-KG', 'MANZANA ROJA', 'Frutas y verduras', 'kg', 32.00, 49.00, [$iva0], true, 5, []],
            ['PLATANO-KG', 'PLÁTANO TABASCO', 'Frutas y verduras', 'kg', 15.00, 24.00, [$iva0], true, 5, []],
            ['JITOMATE-KG', 'JITOMATE SALADET', 'Frutas y verduras', 'kg', 18.00, 30.00, [$iva0], true, 5, []],
            ['CEBOLLA-KG', 'CEBOLLA BLANCA', 'Frutas y verduras', 'kg', 16.00, 26.00, [$iva0], true, 5, []],
            ['BOLILLO', 'BOLILLO', 'Panadería', 'pza', 1.80, 3.50, [$iva0], false, 30, []],
            ['7501000601828', 'PAN BLANCO GRANDE', 'Panadería', 'pza', 34.00, 46.00, [$iva0], false, 6, []],
            ['7501000651717', 'GALLETAS MARÍAS 170 G', 'Botanas y dulces', 'pza', 11.00, 17.00, [$iva0], false, 12, []],
            ['7501011101525', 'PAPAS FRITAS 45 G', 'Botanas y dulces', 'pza', 11.50, 17.00, [$iva16, $ieps], false, 12, []],
            ['7501017111003', 'CHOCOLATE EN BARRA', 'Botanas y dulces', 'pza', 9.00, 15.00, [$iva16, $ieps], false, 12, []],
            ['7501025400041', 'DETERGENTE EN POLVO 1 KG', 'Limpieza', 'pza', 31.00, 44.00, [$iva16], false, 6, []],
            ['7501026010011', 'JABÓN DE BARRA', 'Limpieza', 'pza', 9.50, 15.00, [$iva16], false, 12, []],
            ['7501030400017', 'PAPEL HIGIÉNICO 4 ROLLOS', 'Limpieza', 'pza', 28.00, 40.00, [$iva16], false, 12, []],
            ['7501062420004', 'CLORO 1 L', 'Limpieza', 'pza', 12.00, 19.00, [$iva16], false, 6, []],
        ];

        $partidas = [];

        foreach ($catalogo as [$clave, $descripcion, $depto, $uni, $costo, $precio, $impuestos, $granel, $minimo, $alternas]) {
            $articulo = $guardar(null, [
                'clave' => $clave,
                'descripcion' => $descripcion,
                'departamento_id' => $departamentos[$depto],
                'unidad_medida_id' => $unidad($uni),
                'costo' => (string) $costo,
                'precio_1' => (string) $precio,
                'impuestos' => array_filter($impuestos),
                'claves_alternas' => $alternas,
                'inventariable' => true,
                'granel' => $granel,
                'existencia_minima' => (string) $minimo,
                'existencia_maxima' => (string) ($minimo * 5),
                'activo' => true,
            ], $usuario);

            $partidas[] = [
                'articulo_id' => $articulo->id,
                'cantidad' => (string) ($granel ? $minimo * 3 : $minimo * 3),
                'costo_unitario' => (string) $costo,
            ];
        }

        $proveedor = Proveedor::query()->firstOrCreate(['nombre' => 'Abarrotes del Centro'], ['contacto' => 'Don Raúl', 'telefono' => '5551234567']);

        app(RegistrarCompra::class)([
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->toDateString(),
            'factura' => 'F-0001',
            'notas' => 'Inventario inicial de demostración',
        ], $partidas, $usuario);

        // Dos artículos cerca del mínimo para ver las alertas
        $conAlerta = Articulo::query()->whereIn('clave', ['7501055300075', 'HUEVO-KG'])->get();
        foreach ($conAlerta as $a) {
            app(\App\Actions\Inventario\RegistrarMovimientoInventario::class)(
                $a, \App\Enums\TipoMovimiento::Ajuste, (string) -((float) $a->existencia - (float) $a->existencia_minima + 1),
                usuario: $usuario, motivo: 'correccion', nota: 'Demostración de alerta',
            );
        }
    }
}
