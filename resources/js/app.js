// Livewire 3 ya incluye Alpine.js; no se importa por separado.
import { registrarPos } from './pos';
import { registrarEtiquetas } from './etiquetas';
import { registrarEscaner } from './escaner';

document.addEventListener('alpine:init', () => {
    registrarPos(window.Alpine);
    registrarEtiquetas(window.Alpine);
    registrarEscaner(window.Alpine);
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la app sigue funcionando; solo no es instalable.
        });
    });
}
