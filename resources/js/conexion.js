/*
 * Aviso de falta de internet.
 * La caja necesita conexión (el service worker no guarda páginas ni ventas), así que en lugar de dejar que una
 * pantalla falle en silencio se avisa con una franja arriba y se frena el cambio de pantalla (wire:navigate).
 * `navigator.onLine` en falso es confiable; en verdadero solo dice que hay red, no que haya internet.
 */
export function registrarConexion(Alpine) {
    Alpine.store('conexion', {
        enLinea: navigator.onLine,
        recuperada: false, // «ya volvió» se muestra unos segundos
        llamar: false, // hace parpadear la franja cuando intentan navegar sin conexión
        _t: null,

        init() {
            window.addEventListener('offline', () => this.cambiar(false));
            window.addEventListener('online', () => this.cambiar(true));

            // Sin internet, abrir otra pantalla solo mostraría el error del navegador: se queda donde está.
            document.addEventListener('alpine:navigate', (evento) => {
                if (this.enLinea) return;
                evento.preventDefault();
                this.llamar = true;
                setTimeout(() => (this.llamar = false), 600);
            });
        },

        cambiar(enLinea) {
            const volvio = enLinea && !this.enLinea;
            this.enLinea = enLinea;
            clearTimeout(this._t);
            this.recuperada = volvio;
            if (volvio) this._t = setTimeout(() => (this.recuperada = false), 4000);
        },
    });
}
