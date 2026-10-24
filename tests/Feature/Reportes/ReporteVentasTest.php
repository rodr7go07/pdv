<?php

use App\Actions\Ventas\CancelarVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Departamento;
use App\Models\Impuesto;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteVentas;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja1 = Caja::factory()->create(['serie' => 'A']);
    $this->caja2 = Caja::factory()->create(['serie' => 'B']);
    $this->ana = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Ana']);
    $this->beto = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto']);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    abrirTurno($this->caja1, $this->ana);
    abrirTurno($this->caja2, $this->beto);
    abrirTurno(Caja::factory()->create(['serie' => 'E']), $this->encargado);

    $lacteos = Departamento::factory()->create(['nombre' => 'Lácteos']);
    $abarrotes = Departamento::factory()->create(['nombre' => 'Abarrotes']);
    $iva = Impuesto::where('nombre', 'IVA 16%')->first();

    $this->a = Articulo::factory()->create(['descripcion' => 'ARTICULO A', 'departamento_id' => $lacteos->id, 'precio_1' => '116', 'costo' => '50']);
    $this->a->impuestos()->sync([$iva->id]);
    $this->b = Articulo::factory()->create(['descripcion' => 'ARTICULO B', 'departamento_id' => $abarrotes->id, 'precio_1' => '20', 'costo' => '8']);

    $this->venderComo = fn (User $u, array $lineas, string $pago = '1000') => app(RegistrarVenta::class)(
        Turno::abiertoDe($u)->caja, $u,
        collect($lineas)->map(fn ($c, $id) => ['articulo_id' => $id, 'cantidad' => (string) $c])->values()->all(),
        [['forma' => 'efectivo', 'monto' => $pago]],
    );
});

function periodo(): ReporteVentas
{
    return new ReporteVentas(now()->subDays(3), now());
}

it('calcula el resumen: ventas, utilidad y ticket promedio descontando devoluciones', function () {
    $v1 = ($this->venderComo)($this->ana, [$this->a->id => 1, $this->b->id => 2]);   // 116 + 40 = 156
    $this->travelTo(now()->subDay());
    ($this->venderComo)($this->beto, [$this->b->id => 1]);                            // 20
    $this->travelBack();

    app(RegistrarDevolucion::class)($v1, [['partida_id' => $v1->partidas->firstWhere('articulo_id', $this->b->id)->id, 'cantidad' => '1']], 'Dañado', $this->encargado);

    $r = periodo()->resumen();

    expect($r['ventas'])->toBe(2)
        ->and($r['bruto'])->toBe('176.00')
        ->and($r['devuelto'])->toBe('20.00')
        ->and($r['neto'])->toBe('156.00')
        ->and($r['costo'])->toBe('66.00')
        ->and($r['utilidad'])->toBe('74.00')
        ->and($r['margen'])->toBe('52.9')
        ->and($r['ticket_promedio'])->toBe('88.00');
});

it('las ventas canceladas no cuentan pero se reportan aparte', function () {
    $v = ($this->venderComo)($this->ana, [$this->a->id => 1]);
    ($this->venderComo)($this->ana, [$this->b->id => 1]);
    app(CancelarVenta::class)($v, 'Error', $this->encargado);

    $r = periodo()->resumen();

    expect($r['ventas'])->toBe(1)->and($r['bruto'])->toBe('20.00')->and($r['canceladas'])->toBe(1);
});

it('sin ventas todo es cero y el margen es nulo', function () {
    $r = periodo()->resumen();

    expect($r)->toMatchArray(['ventas' => 0, 'bruto' => '0.00', 'utilidad' => '0.00', 'margen' => null, 'ticket_promedio' => '0.00']);
    expect(periodo()->porArticulo())->toHaveCount(0)->and(periodo()->porCajero())->toHaveCount(0);
});

it('respeta el rango de fechas', function () {
    $this->travelTo(now()->subDays(10));
    ($this->venderComo)($this->ana, [$this->b->id => 1]);
    $this->travelBack();
    ($this->venderComo)($this->ana, [$this->b->id => 3]);

    expect(periodo()->resumen()['ventas'])->toBe(1)
        ->and((new ReporteVentas(now()->subDays(30), now()))->resumen()['ventas'])->toBe(2);
});

it('incluye todo el último día del rango', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 23:30:00'));
    ($this->venderComo)($this->ana, [$this->b->id => 1]);
    $this->travelBack();

    expect((new ReporteVentas('2026-03-10', '2026-03-10'))->resumen()['ventas'])->toBe(1)
        ->and((new ReporteVentas('2026-03-11', '2026-03-12'))->resumen()['ventas'])->toBe(0);
});

it('filtra por caja y por cajero', function () {
    ($this->venderComo)($this->ana, [$this->b->id => 1]);
    ($this->venderComo)($this->beto, [$this->b->id => 2]);

    $porAna = new ReporteVentas(now()->subDay(), now(), userId: $this->ana->id);
    $porCaja2 = new ReporteVentas(now()->subDay(), now(), cajaId: $this->caja2->id);

    expect($porAna->resumen()['bruto'])->toBe('20.00')->and($porCaja2->resumen()['bruto'])->toBe('40.00');
});

it('agrupa por día, semana y mes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 10:00:00')); // lunes
    ($this->venderComo)($this->ana, [$this->b->id => 1]);
    $this->travelTo(CarbonImmutable::parse('2026-03-04 10:00:00')); // miércoles, misma semana
    ($this->venderComo)($this->ana, [$this->b->id => 2]);
    $this->travelTo(CarbonImmutable::parse('2026-03-12 10:00:00')); // otra semana, mismo mes
    ($this->venderComo)($this->ana, [$this->b->id => 3]);
    $this->travelBack();

    $r = new ReporteVentas('2026-03-01', '2026-03-31');

    expect($r->porPeriodo('dia'))->toHaveCount(3)
        ->and($r->porPeriodo('semana')->pluck('bruto')->all())->toBe(['60.00', '60.00'])
        ->and($r->porPeriodo('semana')->first()['ventas'])->toBe(2)
        ->and($r->porPeriodo('mes'))->toHaveCount(1)
        ->and($r->porPeriodo('mes')->first())->toMatchArray(['bruto' => '120.00', 'ventas' => 3, 'periodo' => '2026-03-01']);
});

it('reporta por departamento', function () {
    ($this->venderComo)($this->ana, [$this->a->id => 1, $this->b->id => 2]);

    $filas = periodo()->porDepartamento()->keyBy('nombre');

    expect($filas['Lácteos']['neto'])->toBe('116.00')->and($filas['Lácteos']['utilidad'])->toBe('50.00')
        ->and($filas['Abarrotes']['neto'])->toBe('40.00')->and($filas['Abarrotes']['utilidad'])->toBe('24.00');
});

it('reporta los artículos más vendidos y ordena', function () {
    ($this->venderComo)($this->ana, [$this->a->id => 1, $this->b->id => 10]);

    // A: 1 × 116 = 116 de importe y 50 de utilidad. B: 10 × 20 = 200 de importe y 120 de utilidad.
    expect(periodo()->porArticulo(10, 'importe')->first()['nombre'])->toBe('ARTICULO B')
        ->and(periodo()->porArticulo(10, 'cantidad')->first()['nombre'])->toBe('ARTICULO B')
        ->and(periodo()->porArticulo(10, 'utilidad')->first()['nombre'])->toBe('ARTICULO B') // 120 vs 50
        ->and(periodo()->porArticulo(1)->count())->toBe(1);
});

it('reporta por cajero con su utilidad', function () {
    ($this->venderComo)($this->ana, [$this->a->id => 1]);
    ($this->venderComo)($this->beto, [$this->b->id => 5]);

    $filas = periodo()->porCajero()->keyBy('nombre');

    expect($filas['Ana'])->toMatchArray(['ventas' => 1, 'neto' => '116.00', 'utilidad' => '50.00'])
        ->and($filas['Beto'])->toMatchArray(['ventas' => 1, 'neto' => '100.00', 'utilidad' => '60.00']);
});

it('reporta por forma de pago sin contar el cambio', function () {
    ($this->venderComo)($this->ana, [$this->b->id => 2], '100'); // total 40, recibió 100, cambio 60

    $filas = periodo()->porFormaPago();

    expect($filas)->toHaveCount(1)->and($filas[0])->toMatchArray(['forma' => 'efectivo', 'monto' => '40.00', 'ventas' => 1]);
});

it('la utilidad usa el costo guardado en la partida y no el costo actual', function () {
    ($this->venderComo)($this->ana, [$this->b->id => 1]);          // costo 8 al vender
    $this->b->update(['costo' => '19']);                             // sube el costo después

    expect(periodo()->resumen()['utilidad'])->toBe('12.00');         // 20 − 8, no 20 − 19
});

it('calcula el valor del inventario por departamento', function () {
    $this->a->forceFill(['existencia' => '10'])->save();             // 10 × 50 = 500 costo; 10 × 116 = 1160 venta
    $this->b->forceFill(['existencia' => '5.5'])->save();            // 5.5 × 8 = 44; 5.5 × 20 = 110
    Articulo::factory()->create(['existencia' => 100, 'inventariable' => false, 'costo' => '9']); // no cuenta
    Articulo::factory()->create(['costo' => '9'])->forceFill(['existencia' => '-3'])->save();     // negativa: no cuenta

    $v = ReporteVentas::valorInventario();

    expect($v['costo'])->toBe('544.00')->and($v['venta'])->toBe('1270.00')->and($v['articulos'])->toBe(2)->and($v['filas'])->toHaveCount(2);
});
