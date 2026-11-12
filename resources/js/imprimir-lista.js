import { impresoraBt } from './impresora-bt';

/**
 * Imprime la hoja de existencias de Inventario (decisión 83) por el mismo camino que el ticket en este aparato:
 * Bluetooth directo o RawBT si están activos en «Atajos» de Venta (se guardan por equipo); si no, el navegador.
 */
const leer = (clave) => {
    try { return JSON.parse(localStorage.getItem(clave) || 'null'); } catch { return null; }
};

export function registrarImprimirLista(Alpine) {
    Alpine.data('imprimirLista', (urls) => ({
        imprimiendo: false,
        mensaje: '',
        error: false,

        get modo() {
            if (leer('pdv-imprime-bt') === true && impresoraBt.soportada()) return 'bt';
            if (leer('pdv-imprime-rawbt') === true) return 'rawbt';
            return 'navegador';
        },

        async imprimir(ids) {
            if (!ids.length || this.imprimiendo) return;
            this.imprimiendo = true;
            this.mensaje = '';
            this.error = false;
            const consulta = `?ids=${ids.join(',')}`;
            try {
                if (this.modo === 'navegador') {
                    const marco = document.createElement('iframe');
                    marco.style.cssText = 'position:fixed;width:0;height:0;border:0;visibility:hidden';
                    marco.src = urls.pagina + consulta + '&imprimir=1';
                    document.body.appendChild(marco);
                    setTimeout(() => marco.remove(), 60000);
                    return;
                }

                // Primero la conexión: si hay que elegir la impresora, Chrome exige que sea justo después del toque.
                if (this.modo === 'bt' && !impresoraBt.conectada() && !(await impresoraBt.reconectar())) {
                    await impresoraBt.elegir();
                }

                const r = await fetch(urls.escpos + consulta, { credentials: 'same-origin', signal: AbortSignal.timeout(15000) });
                if (!r.ok) throw new Error('No se pudo preparar la hoja.');
                const datos = (await r.text()).trim();

                if (this.modo === 'rawbt') {
                    window.location.href = `intent:base64,${datos}#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;`;
                } else {
                    await impresoraBt.imprimir(Uint8Array.from(atob(datos), (c) => c.charCodeAt(0)));
                    this.mensaje = 'Hoja enviada a la impresora.';
                }
            } catch (e) {
                this.error = true;
                this.mensaje = e?.name === 'NotFoundError'
                    ? 'No elegiste impresora. Vuelve a tocar Imprimir.'
                    : `No se imprimió: ${e?.message || e}`;
            } finally {
                this.imprimiendo = false;
            }
        },
    }));
}
