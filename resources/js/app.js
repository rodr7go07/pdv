// Livewire 3 ya incluye Alpine.js; no se importa por separado.

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la app sigue funcionando; solo no es instalable.
        });
    });
}
