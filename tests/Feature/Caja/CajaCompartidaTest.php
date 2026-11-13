<?php

use App\Actions\Caja\AbrirTurno;
use App\Actions\Caja\CerrarTurno;
use App\Actions\Caja\RegistrarMovimientoCaja;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use App\Services\ResumenTurno;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->caja = Caja::factory()->create(['serie' => 'A', 'nombre' => 'Mostrador', 'compartida' => true]);
    $this->ana = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Ana']);
    $this->beto = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto']);
    $this->ajeno = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Ajeno']);
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create(['name' => 'Encargada']);
    $this->caja->usuarios()->sync([$this->ana->id, $this->beto->id, $this->encargado->id]);
    $this->articulo = Articulo::factory()->create(['precio_1' => '50', 'costo' => '30', 'existencia' => '100']);
    $this->vender = fn (User $u, string $cantidad = '1') => app(RegistrarVenta::class)(
        $this->caja, $u, [['articulo_id' => $this->articulo->id, 'cantidad' => $cantidad]], [['forma' => 'efectivo', 'monto' => '500']],
    );
});

it('los asignados venden en el mismo turno y cada venta guarda quién vendió', function () {
    $turno = app(AbrirTurno::class)($this->caja, $this->ana, '200');

    $v1 = ($this->vender)($this->ana);
    $v2 = ($this->vender)($this->beto, '3');

    expect($v1->turno_id)->toBe($turno->id)->and($v2->turno_id)->toBe($turno->id)
        ->and($v1->user_id)->toBe($this->ana->id)->and($v2->user_id)->toBe($this->beto->id)
        ->and(Turno::abiertoDe($this->beto)?->id)->toBe($turno->id)
        ->and(Turno::query()->count())->toBe(1);
});

it('quien no está asignado no vende ni abre la caja compartida', function () {
    app(AbrirTurno::class)($this->caja, $this->ana, '0');

    expect(fn () => ($this->vender)($this->ajeno))->toThrow(ValidationException::class)
        ->and(Turno::abiertoDe($this->ajeno))->toBeNull();

    $cerrada = Caja::factory()->create(['serie' => 'Z', 'compartida' => true]);
    expect(fn () => app(AbrirTurno::class)($cerrada, $this->ajeno, '0'))->toThrow(ValidationException::class);
});

it('si la caja compartida ya está abierta, abrirla otra vez entra al mismo turno', function () {
    $turno = app(AbrirTurno::class)($this->caja, $this->ana, '200');

    expect(app(AbrirTurno::class)($this->caja, $this->beto, '999')->id)->toBe($turno->id)
        ->and((float) $turno->fresh()->fondo_inicial)->toBe(200.0);
});

it('el corte lo hace cualquiera con permiso y muestra lo de cada vendedor', function () {
    $turno = app(AbrirTurno::class)($this->caja, $this->ana, '100');
    ($this->vender)($this->ana);
    ($this->vender)($this->beto, '2');
    app(RegistrarMovimientoCaja::class)($turno, 'retiro', '20', 'Pago al gas', $this->encargado);

    $resumen = ResumenTurno::calcular($turno);
    expect($resumen['vendedores'])->toBe([
        ['nombre' => 'Beto', 'cantidad' => 1, 'total' => '100.00'],
        ['nombre' => 'Ana', 'cantidad' => 1, 'total' => '50.00'],
    ])->and($resumen['efectivo_esperado'])->toBe('230.00');

    // Beto no abrió el turno, pero tiene permiso de corte y está asignado.
    $corte = app(CerrarTurno::class)->cerrar($turno, '230', $this->beto);
    expect($corte->tipo)->toBe('Z')->and($turno->fresh()->estado)->toBe('cerrado')
        ->and($corte->datos['vendedores'])->toHaveCount(2);

    $this->actingAs($this->beto)->get(route('caja.corte', $corte))->assertOk()->assertSee('POR VENDEDOR')->assertSee('Beto (1)');
});

it('una caja normal sigue siendo de quien abrió el turno', function () {
    $normal = Caja::factory()->create(['serie' => 'N', 'compartida' => false]);
    $normal->usuarios()->sync([$this->beto->id]);
    app(AbrirTurno::class)($normal, $this->ana, '0');

    expect(fn () => app(RegistrarVenta::class)($normal, $this->beto, [['articulo_id' => $this->articulo->id, 'cantidad' => '1']], [['forma' => 'efectivo', 'monto' => '50']]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AbrirTurno::class)($normal, $this->beto, '0'))->toThrow(ValidationException::class);
});

it('la pantalla Cajas guarda el turno compartido con sus usuarios', function () {
    $admin = User::factory()->conRol(Rol::Administrador)->create();

    Volt::actingAs($admin)->test('pages.cajas')
        ->call('nueva')
        ->set('nombre', 'Caja 2')->set('serie', 'B')->set('compartida', true)
        ->call('guardar')->assertHasErrors('asignados')
        ->set('asignados', [(string) $this->ana->id, (string) $this->beto->id])
        ->call('guardar')->assertHasNoErrors();

    $caja = Caja::query()->where('serie', 'B')->sole();
    expect($caja->compartida)->toBeTrue()->and($caja->usuarios()->pluck('users.id')->sort()->values()->all())->toBe([$this->ana->id, $this->beto->id]);

    // Con el turno abierto no se quita lo compartido.
    app(AbrirTurno::class)($caja, $this->ana, '0');
    Volt::actingAs($admin)->test('pages.cajas')->call('editar', $caja->id)->set('compartida', false)->call('guardar')->assertHasErrors('compartida');
});

it('Mi caja muestra el turno compartido a los asignados y oculta la caja a los demás', function () {
    app(AbrirTurno::class)($this->caja, $this->ana, '0');

    Volt::actingAs($this->beto)->test('pages.caja.index')->assertSee('caja compartida');
    Volt::actingAs($this->ajeno)->test('pages.caja.index')->assertDontSee('Mostrador');
});
