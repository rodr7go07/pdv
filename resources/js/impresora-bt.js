/**
 * Impresora térmica por Bluetooth directo desde Chrome (Web Bluetooth, BLE), sin apps de por medio (decisión 80).
 *
 * Chrome solo deja elegir la impresora con un toque del usuario (`elegir`). La conexión vive mientras la página
 * esté abierta (también al cambiar de pantalla con wire:navigate); si se cae, `imprimir` la reconecta sola.
 * Al abrir Chrome de nuevo se intenta recuperar la última impresora con `getDevices()` (si el navegador lo permite);
 * si no, hay que volver a tocar «Conectar impresora».
 */

// Servicios de escritura de las térmicas baratas más comunes; Chrome solo deja usar los que se declaran.
const SERVICIOS = [
    '000018f0-0000-1000-8000-00805f9b34fb',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    '0000ff00-0000-1000-8000-00805f9b34fb',
    '0000fff0-0000-1000-8000-00805f9b34fb',
    '0000ffe0-0000-1000-8000-00805f9b34fb',
    '0000ae30-0000-1000-8000-00805f9b34fb',
];

const CLAVE = 'pdv-impresora-bt';
const TROZO = 100; // bytes por envío: muchas térmicas BLE solo aceptan paquetes chicos

let dispositivo = null;
let caracteristica = null;
const oyentes = new Set();

const avisarCambio = () => oyentes.forEach((fn) => fn());
const conTiempo = (promesa, ms) => Promise.race([promesa, new Promise((_, no) => setTimeout(() => no(new Error('La impresora no respondió a tiempo.')), ms))]);

async function abrirConexion() {
    const servidor = await conTiempo(dispositivo.gatt.connect(), 10000);
    caracteristica = null;

    for (const servicio of await servidor.getPrimaryServices()) {
        for (const c of await servicio.getCharacteristics()) {
            if (c.properties.write || c.properties.writeWithoutResponse) {
                caracteristica = c;
                break;
            }
        }
        if (caracteristica) break;
    }

    if (!caracteristica) {
        dispositivo.gatt.disconnect();
        throw new Error('Esa impresora no ofrece por dónde recibir el ticket.');
    }

    avisarCambio();
}

function usar(nuevo) {
    if (dispositivo === nuevo) return;
    dispositivo = nuevo;
    dispositivo.addEventListener('gattserverdisconnected', () => { caracteristica = null; avisarCambio(); });
    try { localStorage.setItem(CLAVE, dispositivo.id); } catch { /* sin almacenamiento: no pasa nada */ }
}

export const impresoraBt = {
    soportada: () => !!navigator.bluetooth && window.isSecureContext,
    conectada: () => !!(caracteristica && dispositivo?.gatt?.connected),
    nombre: () => dispositivo?.name || 'Impresora',

    /** Avisa cada que se conecta o se desconecta. Devuelve la función para dejar de escuchar. */
    alCambiar(fn) {
        oyentes.add(fn);
        return () => oyentes.delete(fn);
    },

    /** Abre la lista de Chrome para elegir la impresora. Debe llamarse justo después de un toque. */
    async elegir() {
        usar(await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: SERVICIOS }));
        await abrirConexion();
    },

    /** Vuelve a conectar la impresora ya elegida (o la última, si Chrome la recuerda) sin mostrar la lista. */
    async reconectar() {
        if (this.conectada()) return true;

        if (!dispositivo && navigator.bluetooth?.getDevices) {
            let guardado = null;
            try { guardado = localStorage.getItem(CLAVE); } catch { /* sin almacenamiento */ }
            const conocidos = await navigator.bluetooth.getDevices().catch(() => []);
            const previo = conocidos.find((d) => d.id === guardado);
            if (previo) usar(previo);
        }

        if (!dispositivo) return false;

        try {
            await abrirConexion();
            return true;
        } catch {
            return false;
        }
    },

    /** Manda los bytes ESC/POS a la impresora (la reconecta si se había caído). */
    async imprimir(bytes) {
        if (!this.conectada() && !(await this.reconectar())) {
            throw new Error('La impresora no está conectada.');
        }

        const c = caracteristica;
        for (let i = 0; i < bytes.length; i += TROZO) {
            const trozo = bytes.slice(i, i + TROZO);
            if (c.properties.writeWithoutResponse) {
                await c.writeValueWithoutResponse(trozo);
                await new Promise((r) => setTimeout(r, 30));
            } else {
                await c.writeValueWithResponse(trozo);
            }
        }
    },
};
