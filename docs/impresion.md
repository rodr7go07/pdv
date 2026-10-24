# Impresión de tickets y hardware (Fase 5)

Documento técnico para quien instala el sistema en la tienda. Para el uso diario, ver la **Guía de uso** dentro de la aplicación.

## Qué hay hoy

| Necesidad | Solución actual | Estado |
|---|---|---|
| Ticket de venta 58 mm y 80 mm | Página HTML con CSS de impresión (`window.print()`), según el ancho configurado en cada caja | Listo |
| Corte de caja (X y Z) | Misma técnica, con firma del cajero | Listo |
| Prueba de la impresora | Botón de impresora en **Cajas** imprime un ticket con regla de caracteres | Listo |
| Imprimir sola al cobrar | Casilla «Imprimir el ticket al cobrar» por caja | Listo (ver nota del diálogo) |
| Etiquetas de precio con código de barras | Pantalla **Artículos → Etiquetas** (rollo 50×30 mm o carta en 3 columnas) | Listo, opcional |
| Lectura de báscula | Botón «Leer de la báscula» al vender a granel (Web Serial, solo Chrome/Edge) | **Experimental**: no se probó con una báscula real |
| Abrir el cajón de dinero | Requiere impresión directa (ver QZ Tray) | Pendiente |

## El diálogo de impresión

`window.print()` siempre abre el cuadro de impresión del navegador. Para que imprima **sin preguntar** en la PC de caja:

1. Instala la impresora de tickets y márcala como **predeterminada** en Windows.
2. Crea un acceso directo de Chrome (o Edge) con la bandera `--kiosk-printing`:

   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing --app=https://TU-DOMINIO/venta
   ```

3. Con eso, al cobrar el ticket sale directo a la impresora predeterminada. Marca «Imprimir el ticket al cobrar» en la caja.

Ventajas: cero software extra. Límites: no abre el cajón y depende de que esa PC use ese acceso directo.

En la ventana de impresión (si aparece) desactiva **encabezados y pies de página** y pon los márgenes en **Ninguno**.

## Evaluación de QZ Tray (impresión directa ESC/POS)

[QZ Tray](https://qz.io) es un programa que corre en la PC de caja y deja que el sitio web mande comandos **ESC/POS** directamente a la impresora, sin diálogo.

**Qué aporta**
- Impresión sin diálogo y más rápida (texto crudo, no una página HTML).
- **Apertura del cajón de dinero** (comando de pulso por la impresora).
- Corte de papel y formato fino (negritas, código de barras de la impresora).

**Qué cuesta**
- Instalar y mantener un programa en cada PC de caja (Windows, Java incluido).
- Licencia: la versión gratuita muestra una ventana de confirmación en cada conexión; para evitarla se necesita un certificado de firma (de pago, ~USD 99/año) o la versión comercial.
- Hay que escribir y probar plantillas ESC/POS para cada modelo de impresora.
- Es un punto más que puede fallar el día de caja.

**Recomendación**
1. **Hoy:** usar `--kiosk-printing` (arriba). Resuelve «imprimir sin preguntar» sin costo.
2. **Solo si la tienda necesita que se abra el cajón al cobrar** (o si el ticket HTML sale lento), integrar QZ Tray. El punto de integración sería `imprimirTicket()` en `resources/js/pos.js`: si QZ está disponible, mandar ESC/POS; si no, caer a `window.print()`.
3. Los campos «nombre de impresora» y «abrir cajón» por caja se agregarían entonces (hoy no se guardan porque no se usarían).

Esta decisión se confirma con el dueño según el modelo de impresora y si usa cajón con puerto RJ11 conectado a ella.

## Báscula (Web Serial) — experimental

- Funciona solo en Chrome o Edge y con báscula que envíe el peso por puerto serie/USB-serie.
- Al vender un artículo a granel, el botón «Leer de la báscula» pide elegir el puerto, lee unos segundos y toma el primer número que se repite dos veces seguidas (lectura estable).
- La velocidad por defecto es 9600 baudios. Para cambiarla, en la consola del navegador: `localStorage.setItem('bascula-baud', '4800')`.
- **No se probó con hardware real.** Marcas con protocolo propio pueden requerir ajustar la lectura en `leerBascula()`.

## Etiquetas de precio

- **Artículos → Etiquetas**: elige artículos, copias y formato; imprime con el cuadro del navegador.
- El código de barras es EAN-13 cuando la clave tiene 13 dígitos válidos; si no, Code 128.
- Para rollos de etiquetas térmicas de 50×30 mm, selecciona ese tamaño de papel en el controlador de la impresora.
