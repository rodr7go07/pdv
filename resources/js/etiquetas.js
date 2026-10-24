/*
 * Etiquetas de precio con código de barras. JsBarcode se carga solo cuando se abre esa pantalla.
 * Si la clave tiene 13 dígitos con dígito verificador válido se dibuja como EAN-13; si no, como Code 128.
 */

const ean13Valido = (c) => {
    if (!/^\d{13}$/.test(c)) return false;
    const suma = [...c.slice(0, 12)].reduce((s, d, i) => s + Number(d) * (i % 2 ? 3 : 1), 0);
    return (10 - (suma % 10)) % 10 === Number(c[12]);
};

export function registrarEtiquetas(Alpine) {
    Alpine.data('etiqueta', (clave) => ({
        async dibujar(svg) {
            try {
                const { default: JsBarcode } = await import('jsbarcode');
                JsBarcode(svg, clave, {
                    format: ean13Valido(clave) ? 'EAN13' : 'CODE128',
                    displayValue: false,
                    margin: 0,
                    height: 40,
                    width: 1.6,
                });
            } catch {
                svg.replaceWith(Object.assign(document.createElement('span'), { textContent: clave }));
            }
        },
    }));
}
