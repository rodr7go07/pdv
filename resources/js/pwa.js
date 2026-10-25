/*
 * Instalación de la app (PWA).
 * Chrome, Edge y Android avisan con `beforeinstallprompt`: se guarda el aviso y se muestra un botón
 * «Instalar app» que lo dispara. iPhone/iPad (Safari) no tienen ese aviso: ahí el botón muestra los pasos
 * para agregarla a la pantalla de inicio. Con la app ya instalada el botón desaparece.
 */

const estaInstalada = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

// Safari de iPhone y iPad (los iPad nuevos se identifican como Mac con pantalla táctil).
const esIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

// El aviso puede llegar antes de que Alpine arranque, por eso se escucha desde que carga el módulo.
let avisoGuardado = null;
const alAvisar = [];

window.addEventListener('beforeinstallprompt', (evento) => {
    evento.preventDefault();
    avisoGuardado = evento;
    alAvisar.forEach((f) => f(evento));
});

export function registrarPwa(Alpine) {
    Alpine.store('pwa', {
        aviso: avisoGuardado,
        instalada: estaInstalada(),
        ios: esIOS(),
        ayuda: false,

        init() {
            alAvisar.push((evento) => { this.aviso = evento; });
            window.addEventListener('appinstalled', () => { this.instalada = true; this.aviso = null; this.ayuda = false; });
            window.matchMedia('(display-mode: standalone)').addEventListener?.('change', () => { this.instalada = estaInstalada(); });
        },

        /** Hay algo que ofrecer: el aviso del navegador o, en iPhone, la explicación. */
        get puedeInstalar() {
            return !this.instalada && (this.aviso !== null || this.ios);
        },

        async instalar() {
            if (this.aviso) {
                const aviso = this.aviso;
                this.aviso = null; // el aviso solo se puede usar una vez
                aviso.prompt();
                await aviso.userChoice;
            } else if (this.ios) {
                this.ayuda = true;
            }
        },
    });
}
