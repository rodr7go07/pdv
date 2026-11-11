<?php

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\TicketEscPos;
use Database\Seeders\CatalogosBaseSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([RolesYPermisosSeeder::class, CatalogosBaseSeeder::class]);
    $this->cajero = User::factory()->conRol(Rol::Cajero)->create(['name' => 'Beto Cajero']);
    $this->otro = User::factory()->conRol(Rol::Cajero)->create();
    $this->encargado = User::factory()->conRol(Rol::Encargado)->create();
    $this->caja = Caja::factory()->create(['serie' => 'A', 'ancho_papel' => 58]);
    abrirTurno($this->caja, $this->cajero);
    Configuracion::actual()->update(['nombre_tienda' => 'Abarrotes La Esquina', 'leyenda_ticket' => 'Vuelva pronto']);
    $articulo = Articulo::factory()->create(['descripcion' => 'JALAPEÑOS EN LATA', 'precio_1' => '18']);
    Articulo::query()->whereKey($articulo->id)->update(['existencia' => '50']);

    $this->venta = app(RegistrarVenta::class)(
        $this->caja, $this->cajero, [['articulo_id' => $articulo->id, 'cantidad' => '2']], [['forma' => 'efectivo', 'monto' => '50']],
    );
});

/** Las líneas impresas, sin los comandos ESC/POS. */
function lineasImpresas(string $bytes): array
{
    $sinComandos = preg_replace(['/\x1B[@]/', '/\x1B[taE!d].|\x1D!./s', '/\x1DV../s'], '', $bytes);

    return array_values(array_filter(explode("\n", $sinComandos), fn ($l) => $l !== ''));
}

it('arma el ticket ESC/POS al ancho de 58 mm con el mismo contenido del ticket', function () {
    $bytes = TicketEscPos::generar($this->venta);

    expect($bytes)->toStartWith("\x1B@\x1Bt\x00")      // reinicia y elige PC437
        ->toEndWith("\x1Bd\x04\x1DVB\x00")              // avanza y corta
        ->toContain('ABARROTES LA ESQUINA')->toContain($this->venta->folio)->toContain('Vuelva pronto')
        ->toContain("JALAPE\xA5OS EN LATA")              // la Ñ en PC437
        ->toContain("Atendi\xA2")                        // la ó en PC437
        ->not->toContain('Ñ');

    expect($bytes)->toContain("\x1BE\x01\x1D!\x01TOTAL ");

    foreach (lineasImpresas($bytes) as $linea) {
        expect(strlen($linea))->toBeLessThanOrEqual(32);
    }
});

it('en papel de 80 mm usa 48 columnas', function () {
    $this->caja->update(['ancho_papel' => 80]);
    $bytes = TicketEscPos::generar($this->venta->fresh());

    expect(lineasImpresas($bytes))->toContain(str_repeat('-', 48));
});

it('el ticket para RawBT lo ve el cajero de la venta y quien ve todas, no otro cajero', function () {
    $url = route('ventas.ticket-escpos', $this->venta);

    $r = $this->actingAs($this->cajero)->get($url)->assertOk();
    expect(base64_decode($r->getContent(), true))->toBe(TicketEscPos::generar($this->venta->fresh()));

    $this->actingAs($this->encargado)->get($url)->assertOk();
    $this->actingAs($this->otro)->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect('/login');
});

it('imprime el logo de la tienda como imagen en blanco y negro arriba del ticket', function () {
    Storage::fake('public');
    $imagen = imagecreatetruecolor(100, 50);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 228, 0, 124)); // el rosa cuenta como negro en la térmica
    ob_start();
    imagepng($imagen);
    Storage::disk('public')->put('logos/tienda.png', ob_get_clean());
    Configuracion::actual()->update(['logo_ruta' => 'logos/tienda.png']);
    Cache::forget('configuracion.tienda');

    $bytes = TicketEscPos::generar($this->venta->fresh(), Configuracion::actual());

    // 58 mm: 192 puntos = 24 bytes por fila; la imagen de 100×50 queda en 192×96, toda en negro.
    $cabecera = "\x1Bt\x00\x1Ba\x01\x1Dv0\x00".chr(24)."\x00".chr(96)."\x00";
    expect($bytes)->toContain($cabecera)
        ->and(substr($bytes, strpos($bytes, $cabecera) + strlen($cabecera), 24 * 96))->toBe(str_repeat("\xFF", 24 * 96))
        ->and($bytes)->toContain('ABARROTES LA ESQUINA');
});

it('si el logo no se puede leer, el ticket sale sin él', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/tienda.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    Configuracion::actual()->update(['logo_ruta' => 'logos/tienda.svg']);
    Cache::forget('configuracion.tienda');

    expect(TicketEscPos::generar($this->venta->fresh(), Configuracion::actual()))->not->toContain("\x1Dv0");
});
