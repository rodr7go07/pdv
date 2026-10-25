/*
 * Foto del artículo: tomarla con la cámara o elegirla de la galería, y reducirla en el teléfono
 * antes de subirla (una foto de celular pesa varios MB; el servidor acepta hasta 2 MB).
 */

const LADO_MAXIMO = 1600;
const CALIDAD = 0.85;
// Por debajo de este peso y de este tamaño la foto se sube tal cual.
const PESO_SIN_REDUCIR = 1.5 * 1024 * 1024;
const FORMATOS_ACEPTADOS = ['image/png', 'image/jpeg', 'image/webp'];

async function abrir(archivo) {
    if (window.createImageBitmap) {
        try { return await createImageBitmap(archivo, { imageOrientation: 'from-image' }); } catch { /* se intenta con <img> */ }
    }

    const url = URL.createObjectURL(archivo);
    try {
        const img = new Image();
        img.src = url;
        await img.decode();
        return img;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** Devuelve el archivo reducido a JPEG, o el original si ya es chico o no se puede leer. */
export async function reducirImagen(archivo) {
    if (!archivo.type.startsWith('image/')) return archivo;

    let origen;
    try { origen = await abrir(archivo); } catch { return archivo; }

    const ancho = origen.width;
    const alto = origen.height;
    const escala = Math.min(1, LADO_MAXIMO / Math.max(ancho, alto));

    // Los formatos que el servidor acepta, chicos y ya en tamaño, se suben tal cual; cualquier otro (HEIC, GIF…) se pasa a JPEG.
    if (escala === 1 && archivo.size <= PESO_SIN_REDUCIR && FORMATOS_ACEPTADOS.includes(archivo.type)) {
        origen.close?.();
        return archivo;
    }

    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(ancho * escala);
    lienzo.height = Math.round(alto * escala);
    const ctx = lienzo.getContext('2d');
    // Un PNG con transparencia no debe quedar negro al pasar a JPEG.
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, lienzo.width, lienzo.height);
    ctx.drawImage(origen, 0, 0, lienzo.width, lienzo.height);
    origen.close?.();

    const blob = await new Promise((r) => lienzo.toBlob(r, 'image/jpeg', CALIDAD));
    if (!blob || blob.size >= archivo.size) return archivo;

    return new File([blob], 'foto.jpg', { type: 'image/jpeg', lastModified: Date.now() });
}

export function registrarFoto(Alpine) {
    Alpine.data('fotoArticulo', () => ({
        preparando: false,

        /** Recibe el archivo de la cámara o de la galería, lo reduce y lo sube al componente Livewire. */
        async elegir(evento) {
            const entrada = evento.target;
            const archivo = entrada.files[0];
            entrada.value = ''; // permite volver a elegir el mismo archivo
            if (!archivo) return;

            this.preparando = true;
            try {
                this.$wire.upload('imagen', await reducirImagen(archivo));
            } finally {
                this.preparando = false;
            }
        },
    }));
}
