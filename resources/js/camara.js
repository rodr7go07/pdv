/*
 * Lector de códigos de barras con la cámara del dispositivo (teléfono o tableta).
 * Usa el BarcodeDetector del navegador cuando existe (Chrome en Android) y, si no, ZXing,
 * que se descarga solo la primera vez que se abre la cámara para no cargar de más el punto de venta.
 */

// Solo formatos que se autocomprueban (dígito verificador o suma de control). Code 39, ITF y Codabar no la tienen: con un
// código movido o cortado devuelven «lecturas a medias» que parecen válidas, y las etiquetas del sistema son EAN-13 o Code 128.
const FORMATOS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'qr_code'];
const INTERVALO_MS = 150;
// Mientras el mismo código siga a la vista cuenta una sola vez; para sumar otra pieza hay que apartarlo de la cámara un momento.
const AUSENTE_MS = 2000;
// Al mover rápido la cámara de un producto a otro se leen fragmentos del código que a veces parecen válidos. Un fragmento
// casi nunca se repite, así que un código solo se acepta tras leerlo varias veces dentro de esta ventana de tiempo.
const VENTANA_CONFIRMACION_MS = 1200;
// Los códigos cortos (8 caracteres o menos: EAN-8, UPC-E…) son los que más aparecen por error al leer un fragmento.
const confirmacionesNecesarias = (codigo) => (codigo.length <= 8 ? 3 : 2);

const mensajeDeError = (e) => {
    if (e?.name === 'NotAllowedError' || e?.name === 'SecurityError') return 'No hay permiso para usar la cámara. Actívalo en los permisos del navegador para este sitio.';
    if (e?.name === 'NotFoundError' || e?.name === 'OverconstrainedError') return 'No se encontró una cámara en este dispositivo.';
    if (e?.name === 'NotReadableError') return 'La cámara está ocupada por otra aplicación.';
    return 'No se pudo abrir la cámara.';
};

export const camaraDisponible = () => window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;

/**
 * Abre la cámara en el <video> y llama a alLeer(codigo) por cada código nuevo.
 *
 * @returns {Promise<{detener: () => void, hayLinterna: boolean, linterna: (encender: boolean) => Promise<void>}>}
 */
export async function iniciarLector(video, alLeer) {
    let flujo;
    try {
        flujo = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false,
        });
    } catch (e) {
        throw new Error(mensajeDeError(e));
    }

    let ultimo = '';
    let ultimaVista = 0;
    const lecturas = new Map(); // código → momentos en que se leyó dentro de la ventana de confirmación
    const confirmado = (codigo, ahora) => {
        const marcas = (lecturas.get(codigo) ?? []).filter((t) => ahora - t <= VENTANA_CONFIRMACION_MS);
        marcas.push(ahora);
        lecturas.set(codigo, marcas);
        for (const [otro, momentos] of lecturas) {
            if (ahora - momentos[momentos.length - 1] > VENTANA_CONFIRMACION_MS) lecturas.delete(otro);
        }

        return marcas.length >= confirmacionesNecesarias(codigo);
    };
    const leido = (codigo) => {
        if (!codigo) return;
        const ahora = Date.now();
        if (!confirmado(codigo, ahora)) return;
        const repetido = codigo === ultimo && ahora - ultimaVista < AUSENTE_MS;
        ultimo = codigo;
        ultimaVista = ahora;
        if (!repetido) alLeer(codigo);
    };

    video.srcObject = flujo;
    video.setAttribute('playsinline', 'true');
    video.muted = true;
    try { await video.play(); } catch { /* el navegador puede pausarlo si la ventana ya se cerró */ }

    let detenerLectura;
    try {
        detenerLectura = await empezarLectura(video, flujo, leido);
    } catch (e) {
        flujo.getTracks().forEach((t) => t.stop());
        video.srcObject = null;
        throw e;
    }

    const pista = flujo.getVideoTracks()[0];
    const hayLinterna = !!pista?.getCapabilities?.().torch;

    return {
        hayLinterna,
        async linterna(encender) {
            if (hayLinterna) await pista.applyConstraints({ advanced: [{ torch: encender }] });
        },
        detener() {
            detenerLectura();
            flujo.getTracks().forEach((t) => t.stop());
            video.srcObject = null;
        },
    };
}

async function empezarLectura(video, flujo, leido) {
    if ('BarcodeDetector' in window) {
        const soportados = await window.BarcodeDetector.getSupportedFormats();
        const formats = FORMATOS.filter((f) => soportados.includes(f));

        if (formats.length) {
            const detector = new window.BarcodeDetector({ formats });
            let activo = true;
            const ciclo = async () => {
                while (activo) {
                    try {
                        if (video.readyState >= 2) {
                            const codigos = await detector.detect(video);
                            // La lectura pudo terminar después de cerrar la ventana: ese código ya no cuenta.
                            if (activo && codigos.length) leido(codigos[0].rawValue);
                        }
                    } catch { /* un cuadro que no se pudo leer no detiene la cámara */ }
                    await new Promise((r) => setTimeout(r, INTERVALO_MS));
                }
            };
            ciclo();

            return () => { activo = false; };
        }
    }

    let modulos;
    try {
        modulos = await Promise.all([import('@zxing/browser'), import('@zxing/library')]);
    } catch {
        throw new Error('No se pudo cargar el lector de códigos. Revisa tu conexión e inténtalo de nuevo.');
    }
    const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = modulos;

    const pistas = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E,
            BarcodeFormat.CODE_128, BarcodeFormat.QR_CODE,
        ]],
    ]);
    const lector = new BrowserMultiFormatReader(pistas, { delayBetweenScanAttempts: INTERVALO_MS });
    let detenido = false;
    const controles = await lector.decodeFromStream(flujo, video, (resultado) => {
        if (!detenido && resultado) leido(resultado.getText());
    });

    // controles.stop() también apaga la cámara; aquí solo se detiene la lectura y el cierre lo hace quien llama.
    return () => { detenido = true; controles.stop(); };
}
