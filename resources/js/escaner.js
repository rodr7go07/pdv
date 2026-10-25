/*
 * Escáner de código de barras con la cámara para formularios (teléfono o tableta).
 * Lee un solo código: cierra la ventana y avisa con el evento `codigo-escaneado`
 * ({ destino, codigo }) para que el campo que lo pidió lo reciba.
 * Se abre con el evento `abrir-camara` ({ destino }). Ver <x-escaner-camara>.
 */
import { camaraDisponible, iniciarLector } from './camara';

// Destinos cuya pantalla no consulta al servidor al recibir el código: aquí se avisa para la bitácora de escaneos.
const ORIGEN_EN_BITACORA = { clave: 'articulo_clave', alterna: 'articulo_clave', 'buscar-inventario': 'inventario' };

// Último código leído con la cámara: si en unos segundos se da Enter en el campo ya lleno, no se anota otra vez.
const ESPERA_ENTER_MS = 10000;
let ultimoPorCamara = { codigo: '', en: 0 };

/**
 * Anota un código en la bitácora de escaneos (`POST /escaneos`). `medio`: 'lector' (lector USB o teclado + Enter) o 'camara'.
 * También está en `window.anotarEscaneo` para los campos que reciben el código por Enter.
 */
export function anotarEscaneo(origen, codigo, medio = 'lector') {
    codigo = String(codigo ?? '').trim();
    if (!codigo) return;
    if (medio === 'lector' && codigo === ultimoPorCamara.codigo && Date.now() - ultimoPorCamara.en < ESPERA_ENTER_MS) return;
    if (medio === 'camara') ultimoPorCamara = { codigo, en: Date.now() };

    fetch('/escaneos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ codigo, origen, medio }),
    }).catch(() => { /* la bitácora nunca debe estorbar al escanear */ });
}

window.anotarEscaneo = anotarEscaneo;

export function registrarEscaner(Alpine) {
    Alpine.data('escanerCamara', () => ({
        abierto: false,
        iniciando: false,
        error: '',
        hayLinterna: false,
        linterna: false,
        destino: '',
        _lector: null,
        _id: 0, // identifica cada apertura: un arranque viejo (permiso tardío) no pisa a uno nuevo

        /** Alpine lo llama al salir de la pantalla (wire:navigate): la cámara no debe quedar encendida. */
        destroy() {
            this.cerrar();
        },

        abrir(destino) {
            this._id++;
            this.destino = destino || '';
            this.error = '';
            this.hayLinterna = false;
            this.linterna = false;
            this.abierto = true;

            if (!camaraDisponible()) {
                this.error = 'La cámara solo funciona con conexión segura (HTTPS). Abre el sistema con https://.';
                return;
            }

            this.iniciando = true;
            const id = this._id;
            this.$nextTick(() => this.arrancar(id));
        },

        async arrancar(id) {
            try {
                const lector = await iniciarLector(this.$refs.video, (codigo) => this.leido(codigo));
                // Si cerraron la ventana (o abrieron otra vez) mientras se pedía el permiso, este arranque se apaga de inmediato.
                if (id !== this._id || !this.abierto) { lector.detener(); return; }
                this._lector = lector;
                this.hayLinterna = lector.hayLinterna;
            } catch (e) {
                if (id === this._id) this.error = e.message;
            } finally {
                if (id === this._id) this.iniciando = false;
            }
        },

        leido(codigo) {
            if (!this.abierto) return; // llegó tarde: la ventana ya se cerró
            const destino = this.destino;
            navigator.vibrate?.(60);
            this.cerrar();
            if (ORIGEN_EN_BITACORA[destino]) anotarEscaneo(ORIGEN_EN_BITACORA[destino], codigo, 'camara');
            this.$dispatch('codigo-escaneado', { destino, codigo });
        },

        cerrar() {
            this._id++;
            this._lector?.detener();
            this._lector = null;
            this.abierto = false;
            this.iniciando = false;
        },

        async alternarLinterna() {
            try {
                await this._lector?.linterna(!this.linterna);
                this.linterna = !this.linterna;
            } catch { this.error = 'No se pudo cambiar la linterna.'; }
        },
    }));
}
