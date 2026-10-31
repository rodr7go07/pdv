/*
 * Compartir el ticket de una venta como texto (WhatsApp, correo, mensajes…).
 * En el teléfono abre el menú de compartir del sistema; donde no existe (computadora) lo copia al portapapeles.
 * Devuelve 'compartido', 'cancelado' o 'copiado'; lanza un Error con un mensaje para mostrar si algo falla.
 */
export async function compartirTicket(url, folio) {
    const r = await fetch(url, { headers: { Accept: 'text/plain', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!r.ok) throw new Error(r.status === 401 || r.status === 419 ? 'Tu sesión expiró. Vuelve a iniciar sesión.' : 'No se pudo preparar el ticket.');

    const texto = await r.text();

    if (navigator.share) {
        try {
            await navigator.share({ title: `Ticket ${folio}`, text: texto });
            return 'compartido';
        } catch (e) {
            if (e.name === 'AbortError') return 'cancelado';
            // Si el menú de compartir falla por otra razón, se intenta copiar.
        }
    }

    try {
        await navigator.clipboard.writeText(texto);
        return 'copiado';
    } catch {
        throw new Error('Este navegador no deja compartir ni copiar el ticket.');
    }
}

window.compartirTicket = compartirTicket;
