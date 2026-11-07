/*
 * Prueba de humo de la pantalla de Venta en un navegador real (Playwright). Las pruebas de Pest no ejecutan el
 * JavaScript de la caja, y ahí es donde un cambio puede romper el escaneo sin que nada más avise.
 * Correrla ANTES de entregar cualquier cambio en resources/js/pos.js, en pos.blade.php o en lo que usa la caja.
 *
 *   node tests/humo/venta.cjs <url> <usuario> <contraseña> <clave-de-un-artículo-existente>
 *   ej.: node tests/humo/venta.cjs http://localhost:8000 encargado password 7501055363057
 *
 * Necesita un servidor levantado (php artisan serve con una base de prueba), un usuario con turno de caja abierto
 * y permiso de descuentos, y un artículo con esa clave. Termina con código 1 si algo falla.
 */
const { chromium } = require('playwright');

const [url, usuario, clave, codigo] = process.argv.slice(2);
if (!url || !usuario || !clave || !codigo) {
    console.error('Uso: node tests/humo/venta.cjs <url> <usuario> <contraseña> <clave-de-un-artículo-existente>');
    process.exit(2);
}

const fallos = [];
const comprobar = (nombre, ok, detalle = '') => {
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? ' — ' + detalle : ''}`);
    if (!ok) fallos.push(nombre);
};

(async () => {
    const navegador = await chromium.launch();
    const contexto = await navegador.newContext({ viewport: { width: 1280, height: 800 } });
    const p = await contexto.newPage();
    const errores = [];
    p.on('pageerror', (e) => errores.push(e.message));

    await p.goto(`${url}/login`);
    await p.fill('input[type=text]', usuario);
    await p.fill('input[type=password]', clave);
    await p.click('button[type=submit]');
    await p.waitForTimeout(1800);
    await p.goto(`${url}/venta`);
    await p.waitForTimeout(1500);

    const campo = p.locator('input[aria-label="Código del artículo"]');
    const estado = () => p.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-ref="lista"]'));
        return { lineas: d.lineas.length, piezas: d.piezas, aviso: d.aviso, modal: d.modal };
    });

    comprobar('la pantalla de Venta abre con el campo de captura', await campo.isVisible());

    // 1) Lector o teclado: código + Enter agrega el artículo.
    await campo.fill(codigo);
    await campo.press('Enter');
    await p.waitForTimeout(900);
    comprobar('código + Enter agrega el artículo', (await estado()).lineas === 1);

    // 2) Segunda lectura del mismo código suma una pieza.
    await campo.fill(codigo);
    await campo.press('Enter');
    await p.waitForTimeout(900);
    comprobar('escanear otra vez suma una pieza', (await estado()).piezas === 2);

    // 3) Cámara: la misma ruta que usa la lectura de la cámara (sin necesitar una cámara).
    await p.evaluate((c) => { const d = window.Alpine.$data(document.querySelector('[x-ref="lista"]')); d.modal = 'camara'; d.codigoDeCamara(c); }, codigo);
    await p.waitForTimeout(1200);
    const tras = await estado();
    comprobar('una lectura de la cámara agrega el artículo', tras.piezas === 3, `piezas: ${tras.piezas}`);
    comprobar('la cámara muestra el código leído', (await p.evaluate(() => window.Alpine.$data(document.querySelector('[x-ref="lista"]')).ultimoCodigo)) === codigo);
    await p.keyboard.press('Escape');
    await p.waitForTimeout(300);

    // 4) Coincidencias mientras se escribe.
    // Ojo: fill('') de Playwright manda una tecla Supr, y con el campo vacío Supr quita el artículo seleccionado (atajo de la caja).
    await campo.click();
    await campo.type(codigo.slice(0, 6), { delay: 70 });
    await p.waitForTimeout(800);
    comprobar('al escribir aparecen coincidencias', await p.locator('#sugerencias-venta').isVisible());
    await campo.press('Escape');
    await p.evaluate(() => { window.Alpine.$data(document.querySelector('[x-ref="lista"]')).entrada = ''; });

    // 5) Cobro: abre, ofrece el descuento por efectivo y se puede confirmar.
    await p.getByRole('button', { name: /^COBRAR|Cobrar/ }).first().click();
    await p.waitForTimeout(1500);
    const interruptor = p.getByRole('switch', { name: 'Descuento por pago en efectivo' });
    comprobar('el cobro abre con el total', await p.locator('text=Total a cobrar').isVisible());
    if (await interruptor.isVisible()) {
        await interruptor.click();
        await p.waitForTimeout(1200);
        comprobar('el descuento por efectivo muestra el total sin descuento', await p.locator('text=/Sin descuento:/').isVisible());
    }
    await p.keyboard.press('Enter');
    await p.waitForTimeout(2000);
    comprobar('confirmar el cobro registra la venta', await p.locator('text=/Nueva venta/').first().isVisible());

    // 6) Compartir el ticket en PDF desde la ventana de venta cobrada (en la computadora lo descarga).
    const [descarga] = await Promise.all([
        p.waitForEvent('download', { timeout: 15000 }).catch(() => null),
        p.getByRole('button', { name: /Compartir PDF/ }).click(),
    ]);
    comprobar('Compartir PDF descarga el ticket como PDF', !!descarga && /^ticket-.*\.pdf$/.test(descarga.suggestedFilename()), descarga ? descarga.suggestedFilename() : 'sin descarga');

    // 7) Nada tronó en la consola.
    comprobar('sin errores de JavaScript en la página', errores.length === 0, errores.join(' | '));

    await navegador.close();
    console.log(fallos.length ? `\n${fallos.length} comprobación(es) fallaron.` : '\nTodo en orden.');
    process.exit(fallos.length ? 1 : 0);
})();
