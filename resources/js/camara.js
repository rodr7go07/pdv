/*
 * Lector de códigos de barras con la cámara del dispositivo (teléfono o tableta).
 * Usa el BarcodeDetector del navegador cuando existe (Chrome en Android) y, si no, ZXing,
 * que se descarga solo la primera vez que se abre la cámara para no cargar de más el punto de venta.
 */

const FORMATOS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'codabar', 'qr_code'];
const INTERVALO_MS = 150;
// Mientras el mismo código siga a la vista cuenta una sola vez; para sumar otra pieza hay que apartarlo de la cámara un momento.
const AUSENTE_MS = 1500;

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
    const leido = (codigo) => {
        if (!codigo) return;
        const ahora = Date.now();
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
                            if (codigos.length) leido(codigos[0].rawValue);
                        }
                    } catch { /* un cuadro que no se pudo leer no detiene la cámara */ }
                    await new Promise((r) => setTimeout(r, INTERVALO_MS));
                }
            };
            ciclo();

            return () => { activo = false; };
        }
    }

    const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
        import('@zxing/browser'),
        import('@zxing/library'),
    ]);

    const pistas = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E,
            BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.ITF, BarcodeFormat.CODABAR, BarcodeFormat.QR_CODE,
        ]],
    ]);
    const lector = new BrowserMultiFormatReader(pistas, { delayBetweenScanAttempts: INTERVALO_MS });
    const controles = await lector.decodeFromStream(flujo, video, (resultado) => {
        if (resultado) leido(resultado.getText());
    });

    // controles.stop() también apaga la cámara; aquí solo se detiene la lectura y el cierre lo hace quien llama.
    return () => controles.stop();
}
