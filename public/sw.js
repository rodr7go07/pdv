/*
 * Service worker del punto de venta.
 *
 * Solo hace dos cosas: guardar los archivos estáticos (CSS, JS, fuentes e íconos)
 * para que la app abra rápido, y mostrar una página informativa cuando no hay
 * internet. NO guarda páginas ni respuestas de Livewire: la operación de caja
 * siempre requiere conexión para no vender con datos desactualizados.
 */
const VERSION = 'pdv-v1';
const CACHE_ESTATICOS = `${VERSION}-estaticos`;
const PAGINA_OFFLINE = '/offline.html';

const PRECACHE = [
    PAGINA_OFFLINE,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icono.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_ESTATICOS)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(
                claves.filter((clave) => clave !== CACHE_ESTATICOS).map((clave) => caches.delete(clave)),
            ))
            .then(() => self.clients.claim()),
    );
});

const esEstatico = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Navegación: siempre a la red; sin internet, página informativa.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(PAGINA_OFFLINE)));
        return;
    }

    // Archivos compilados (nombre con hash) e íconos: primero caché. Los íconos no llevan hash: si cambian, sube VERSION.
    if (esEstatico(url)) {
        event.respondWith(
            caches.match(request).then((guardado) => {
                if (guardado) return guardado;

                return fetch(request).then((respuesta) => {
                    if (respuesta.ok) {
                        const copia = respuesta.clone();
                        caches.open(CACHE_ESTATICOS).then((cache) => cache.put(request, copia));
                    }
                    return respuesta;
                });
            }),
        );
    }
});
