/*
 * Modo oscuro. La preferencia se guarda en este equipo (localStorage, clave «tema») y se aplica antes de pintar
 * con el script del <head> (layouts/partials/head.blade.php); la primera vez sigue el tema del sistema operativo.
 * Aquí vive solo el interruptor: la clase `dark` va en <html> y los colores salen de resources/css/app.css.
 */
const CLAVE = 'tema';
const COLOR_BARRA = { claro: '#ffffff', oscuro: '#1c1a1f' };

function guardado() {
    try {
        return localStorage.getItem(CLAVE);
    } catch {
        return null;
    }
}

function oscuroInicial() {
    const g = guardado();
    return g ? g === 'oscuro' : window.matchMedia?.('(prefers-color-scheme: dark)').matches === true;
}

function aplicar(oscuro) {
    document.documentElement.classList.toggle('dark', oscuro);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', oscuro ? COLOR_BARRA.oscuro : COLOR_BARRA.claro);
}

export function registrarTema(Alpine) {
    Alpine.store('tema', {
        oscuro: oscuroInicial(),

        init() {
            aplicar(this.oscuro);

            // Al cambiar de pantalla con wire:navigate, Livewire cambia los atributos de <html> y se pierde la clase: se vuelve a poner.
            document.addEventListener('livewire:navigated', () => aplicar(this.oscuro));

            // Si se cambia en otra pestaña, esta se pone igual.
            window.addEventListener('storage', (e) => {
                if (e.key !== CLAVE) return;
                this.oscuro = e.newValue === 'oscuro';
                aplicar(this.oscuro);
            });
        },

        alternar() {
            this.oscuro = !this.oscuro;
            aplicar(this.oscuro);
            try {
                localStorage.setItem(CLAVE, this.oscuro ? 'oscuro' : 'claro');
            } catch {
                // Sin almacenamiento (ventana privada) el cambio vale solo mientras esta pantalla siga abierta.
            }
        },
    });
}
