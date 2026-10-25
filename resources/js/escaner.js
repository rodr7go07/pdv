/*
 * Escáner de código de barras con la cámara para formularios (teléfono o tableta).
 * Lee un solo código: cierra la ventana y avisa con el evento `codigo-escaneado`
 * ({ destino, codigo }) para que el campo que lo pidió lo reciba.
 * Se abre con el evento `abrir-camara` ({ destino }). Ver <x-escaner-camara>.
 */
import { camaraDisponible, iniciarLector } from './camara';

// Destinos cuya pantalla no consulta al servidor al recibir el código: aquí se avisa para la bitácora de escaneos.
const ORIGEN_EN_BITACORA = { clave: 'articulo_clave', alterna: 'articulo_clave', 'buscar-inventario': 'inventario' };

function anotarEscaneo(destino, codigo) {
    const origen = ORIGEN_EN_BITACORA[destino];
    if (!origen) return;

    fetch('/escaneos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ codigo, origen }),
    }).catch(() => { /* la bitácora nunca debe estorbar al escanear */ });
}

export function registrarEscaner(Alpine) {
    Alpine.data('escanerCamara', () => ({
        abierto: false,
        iniciando: false,
        error: '',
        hayLinterna: false,
        linterna: false,
        destino: '',
        _lector: null,

        abrir(destino) {
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
            this.$nextTick(() => this.arrancar());
        },

        async arrancar() {
            try {
                const lector = await iniciarLector(this.$refs.video, (codigo) => this.leido(codigo));
                // Si cerraron la ventana mientras se pedía el permiso, se apaga de inmediato.
                if (!this.abierto) { lector.detener(); return; }
                this._lector = lector;
                this.hayLinterna = lector.hayLinterna;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.iniciando = false;
            }
        },

        leido(codigo) {
            const destino = this.destino;
            navigator.vibrate?.(60);
            this.cerrar();
            anotarEscaneo(destino, codigo);
            this.$dispatch('codigo-escaneado', { destino, codigo });
        },

        cerrar() {
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
