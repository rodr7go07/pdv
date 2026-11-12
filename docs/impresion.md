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

## Logotipo de la tienda

El logo de *Configuración* sale en el ticket HTML, en el PDF y en el ticket ESC/POS (Bluetooth y RawBT), este último como imagen de puntos en blanco y negro (`TicketEscPos::logo`, en caché por archivo y ancho). El logo de **Miscelánea Emmanuel** (tiendita con toldo rosa) y sus versiones están en `docs/marca/`: `icono.png`/`icono.svg` (el que se sube a Configuración), `icono-termica.*` (en negro) y `logo-completo.png` (ícono con el nombre, para letrero o redes).

## Visita a la tienda: qué probar y qué opciones hay

Equipo de la tienda: la **tablet Android es la caja**, con la térmica **Moon 58W** (58 mm) ya vinculada en la app nativa SicarX (USB y/o Bluetooth), una báscula (STM32 Virtual COM) y un lector «Checko2B». SicarX habla con la impresora directamente; Chrome no, solo lista las impresoras registradas en los servicios de impresión de Android. Por eso el cuadro de impresión de Chrome sale sin la térmica.

### Lector de códigos (Bluetooth, en PC o tablet)
1. Debe estar en modo **teclado HID** (no SPP) y mandar **Enter** al final de cada lectura (sufijo CR/LF); sin lotes ni memoria.
2. Distribución del teclado igual a la del equipo (si sale un símbolo en lugar de un número, es la distribución).
3. Probarlo primero en el Bloc de notas o en un campo de texto: debe escribir el código y saltar de línea.
4. En **Venta** captura sin tener el cursor en el campo; en las demás pantallas el cursor debe estar en el campo.
5. Alternativa: la cámara (necesita https). Muestra «Código leído»; UPC-A y EAN-13 con un 0 al frente se consideran el mismo código.

### Impresión desde la tablet (Android) con la Moon 58W
1. **RawBT (primero probar esto, no requiere código):** instalar RawBT, vincular la impresora (Bluetooth emparejado antes en Android, o USB con adaptador OTG), papel 58 mm, imprimir la página de prueba, activar el servicio de impresión de RawBT en Ajustes → Impresión. Luego, en el sistema, «Imprimir ticket» → elegir RawBT. Cerrar SicarX durante la prueba: solo una app puede tener la conexión. Costo: aparece el cuadro de impresión cada vez.
2. **Impresión de un toque con RawBT (construida, decisión 79):** la opción 1 ya funcionó en el teléfono. En la pantalla de Venta, *Atajos* → «Imprimir directo con RawBT» (por equipo). *Imprimir ticket* manda el ticket en ESC/POS (`TicketEscPos`, desde el texto de `TicketTexto`) a RawBT y sale sin cuadro. Ancho según *Cajas* (58 mm = 32 columnas). Corta el papel al final; el cajón no se abre (el dueño no lo pidió). La impresora de la tienda resultó ser una térmica marca SICAR de 58 mm con un cajón conectado debajo.
3. **Bluetooth directo desde Chrome (construido, decisión 80):** la térmica de la tienda (Printer001) sí acepta BLE: la prueba de `/impresora-bluetooth` imprimió. Se eligió porque el «imprimir sin preguntar» de RawBT es de paga. En Venta: *Atajos* → «Imprimir por Bluetooth directo»; el botón de impresora de la cabecera conecta (verde/rojo). Al cerrar Chrome puede pedir tocarlo otra vez.
4. **Imprimir en la caja (ya construido, decisión 73):** desde el teléfono se pide la impresión y la pantalla de Venta de la PC de esa caja la atiende sola o avisa. Requiere una PC con la impresora.
5. **PC con Chrome `--kiosk-printing`** y la térmica como predeterminada de Windows: imprime sin cuadro (ya documentado arriba; falta probar con la impresora real).
6. **Compartir ticket** por WhatsApp u otra app (ya construido).
7. QZ Tray solo hace falta si se quiere abrir el **cajón de dinero** desde una PC (no integrado).

### Resultado de las pruebas
- **Lector:** vinculado por Bluetooth a la tablet, ya venía en modo teclado con Enter: en Venta escanea, busca y agrega el artículo sin tocar nada.
- **Impresora:** SICAR de 58 mm (aparece como Printer001), acepta BLE: imprime de un toque por Bluetooth directo, con logo (decisiones 80 y 81).

### Datos que hay que traer de la tienda
- ¿La térmica está por USB o por Bluetooth (o ambos)?
- ¿RawBT imprime la página de prueba? ¿Y un ticket del sistema? ¿Cómo se ve a 58 mm (en el cuadro de Android, tamaño de papel)?
- ¿El lector escribe el código y manda Enter en el Bloc de notas? ¿Funciona en Venta sin tocar el campo?
- ¿Se necesita abrir el cajón de dinero?
