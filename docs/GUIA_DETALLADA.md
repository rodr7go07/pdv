# Guía detallada del Punto de Venta

Primera redacción de la guía de uso: completa y con los términos del sistema. La versión en lenguaje sencillo es la que se ve dentro de la aplicación, en **Guía de uso** (menú lateral).

---

## 1. Conceptos básicos

**Usuario y rol.** Cada persona entra con su *usuario* (o su correo) y su contraseña. El *rol* define lo que puede hacer:

| Rol | Pensado para | Puede |
|---|---|---|
| Administrador | El dueño | Todo, incluidos usuarios y configuración |
| Encargado | La persona de confianza que supervisa | Todo menos administrar usuarios y la configuración de la tienda |
| Cajero | Quien cobra | Abrir y cerrar su turno, cobrar, consultar artículos, ver sus ventas y recibir abonos |

**Turno.** Antes de vender, el cajero *abre su turno* en una caja con un *fondo inicial* (el efectivo que deja en el cajón para dar cambio). Todo lo que cobre queda ligado a ese turno. Al terminar, *cierra el turno* contando el dinero (arqueo). Solo puede haber un turno abierto por caja y por persona.

**Existencia y movimientos.** La cantidad de cada artículo nunca se escribe a mano: cambia únicamente cuando ocurre algo (compra, venta, devolución, cancelación, ajuste o conteo físico). Cada cambio queda en el *kardex* del artículo con fecha, cantidad y responsable.

**Nada se borra.** Las ventas y las compras se *cancelan* (con motivo, usuario y fecha), los catálogos se *desactivan*. Así el historial siempre cuadra.

**Dinero.** Los precios de venta *ya incluyen impuestos*. El costo se captura *sin impuestos*.

---

## 2. Primera vez: dejar la tienda lista (Administrador)

Haz estos pasos en orden:

1. **Cambia tu contraseña** en *Mi cuenta*.
2. **Configuración:** nombre de la tienda, razón social, RFC, dirección, teléfono, leyenda del ticket y logotipo. A la derecha ves cómo queda el ticket.
   - *Costo que se usa para la utilidad*: **costo promedio** (mezcla lo que tenías con lo nuevo) o **último costo** (lo que pagaste la última vez). Se guardan siempre los dos; esta opción solo decide cuál se usa para márgenes y utilidad.
   - *Permitir vender aunque no haya existencia*: útil si a veces vendes antes de capturar la compra.
3. **Catálogos:** crea tus departamentos (solo viene «General») y revisa las unidades de medida y los impuestos que ya vienen (IVA 16 %, IVA 0 %, Exento, IEPS 8 %). Agrega los que falten.
4. **Cajas:** revisa la *Caja 1* (serie A). Elige el ancho del papel de tu impresora (58 u 80 mm) y si el ticket se imprime solo. El botón de impresora imprime una hoja de prueba.
5. **Usuarios:** da de alta a tus cajeros y encargados (usuario, nombre, contraseña y rol). Entrégales su usuario y contraseña.
6. **Proveedores:** registra a quienes te surten.
7. **Artículos:** crea tus artículos uno por uno o impórtalos de Excel (sección 3).
8. **Inventario inicial:** registra una *compra* con lo que ya tienes en bodega (o haz un *conteo físico*, sección 6). Sin esto la existencia empieza en cero.

---

## 3. Artículos

### Crear un artículo
*Artículos → Nuevo.* Campos importantes:

- **Clave principal:** el código de barras (puedes escanearlo en el campo). No se puede repetir.
- **Claves alternas:** otros códigos del mismo artículo (por ejemplo otra presentación). Escribe cada uno y presiona Enter. Tampoco se pueden repetir con ninguna otra clave del sistema.
- **Departamento y unidad de medida.**
- **Orden recomendado:** elige primero el IVA (viene en 16 %), después escribe costo y precio. Ejemplo: costo $20 con margen 35 % da $27.00 con IVA 0 % y $31.32 con IVA 16 %, porque el precio de venta incluye el impuesto.
- **Costo, margen y precio.** Los tres están enlazados: si cambias el costo o el margen, el sistema sugiere el precio; si cambias el precio, calcula el margen. El margen es la utilidad sobre el costo, calculada con el precio sin impuestos.
- **IVA e IEPS.** Un artículo lleva como máximo un IVA y puede llevar IEPS.
- **Precios 2 y 3 (mayoreo):** opcionales; se usan con clientes que tengan esa lista de precios.
- **Inventario:** si el artículo se cuenta o no, existencia mínima (para las alertas) y máxima.
- **Existencia inicial:** solo al crear (y con permiso de ajustar inventario): cuánto hay hoy. Se anota en el historial como «Existencia inicial»; después la existencia solo cambia con compras, ventas, ajustes y conteos.
- **A granel:** se vende por peso o medida; la unidad debe permitir decimales (kg, litro, metro).

Cada cambio de costo o de precio queda en el **historial** al final de la pantalla.

### Importar desde Excel
*Artículos → Importar.* Descarga la plantilla, llénala y súbela. La plantilla trae instrucciones y la lista de departamentos, unidades e impuestos.
- Si una **clave ya existe**, se actualiza ese artículo (las celdas vacías no borran el dato actual).
- Los departamentos nuevos se crean solos; las unidades y los impuestos deben existir.
- Es **todo o nada**: si una fila tiene un error no se guarda ninguna y el sistema te dice la fila exacta y qué corregir.

### Etiquetas de precio
*Artículos → Etiquetas.* Elige los artículos y las copias; imprime en rollo de 50×30 mm o en hoja carta.

---

## 4. Vender (Cajero)

### Abrir el turno
*Mi caja → Abrir turno:* elige la caja, escribe el fondo inicial y abre. Hasta entonces la pantalla de venta te pide abrirlo.

### La pantalla de venta
Pantalla completa pensada para escáner y teclado. El cursor siempre está en el campo de captura.

| Quieres… | Haz esto |
|---|---|
| Agregar un artículo | Escanea el código (o escríbelo) y Enter. Si lo escaneas otra vez suma una pieza. |
| Escanear con la cámara | En teléfono y tableta (pantallas menores a 1024 px) hay un botón de cámara junto al campo de captura. Abre una ventana con la cámara trasera, lee EAN, UPC, Code 128/39, ITF, Codabar y QR, y agrega cada artículo sin cerrarse. Un código que sigue a la vista cuenta una sola vez; para sumar otra pieza hay que apartarlo ~1.5 s. Incluye linterna si el dispositivo la tiene. **Requiere HTTPS** (o `localhost`): en HTTP el navegador no da acceso a la cámara. Usa el `BarcodeDetector` del navegador (Chrome en Android) y, si no existe (iPhone), ZXing, que se descarga solo al abrir la cámara. |
| Varias piezas | Escribe `5*` y escanea, o `5*7501055300075`. |
| Vender a granel | Escanea el artículo e indica el peso. Si tu navegador lo permite, hay un botón para leer la báscula (experimental). |
| Buscar por nombre | **F2** o el botón *Buscar*. |
| Cambiar cantidad | Los botones **+ / −**, o escribe la cantidad en su casilla. |
| Quitar un artículo | El bote de basura, o **Supr** con el artículo seleccionado (↑ ↓ para moverte). |
| Descuento a un artículo o a toda la venta | El botón de porcentaje en cada renglón, o **F6** para toda la venta (solo con permiso). |
| Elegir un cliente | **F7** (usa su lista de precios y su crédito). |
| Dejar la venta en espera | **F3**; recupérala con **F4**. |
| Cobrar | **F8** o *Cobrar*. |

**Artículo que no existe:** si tienes permiso, la pantalla te ofrece darlo de alta en el momento.

### Cobrar
La ventana de cobro muestra el total exacto. Puedes cobrar con **efectivo, tarjeta, transferencia o a crédito**, solos o mezclados:
- *Efectivo:* escribe cuánto te dan (o usa los botones de billetes) y verás el cambio.
- *Tarjeta, transferencia y crédito* se cobran por el monto exacto; solo el efectivo genera cambio.
- Al confirmar aparece el folio y el cambio a entregar. Puedes imprimir el ticket.

Si no hay internet al cobrar, la venta **no se registra**: espera a que regrese y vuelve a intentar (el carrito se conserva).

### Durante el turno
- **Entrada de efectivo:** dinero que agregas al cajón (por ejemplo, cambio del banco).
- **Retiro de efectivo:** dinero que sacas (pago a proveedor, depósito). Siempre con motivo; no puedes retirar más de lo que hay.
- **Corte parcial (X):** una foto de cómo va el turno, sin cerrarlo. Se puede imprimir.

### Cerrar el turno
*Mi caja → Cerrar turno:* cuenta billetes y monedas y escribe el total. El sistema calcula si **cuadra, sobra o falta** y genera el **corte final (Z)** para imprimir y firmar. Después ya no se puede vender en ese turno.

> El efectivo esperado = fondo + efectivo de ventas + abonos en efectivo + entradas − retiros − devoluciones. Tarjeta y transferencia no entran al cajón. El cajero no ve el efectivo esperado antes de contar (corte «ciego»).

---

## 5. Cancelar y devolver

- **Cancelar una venta** (Encargado/Administrador): *Ventas →* abre la venta → *Cancelar venta* con motivo. Regresa el inventario. **Solo se puede mientras el turno de esa venta siga abierto** y si todavía no tiene devoluciones.
- **Devolución** (parcial o total): *Ventas →* abre la venta → *Devolución*, indica cuántas piezas regresan. Se reembolsa lo que el cliente realmente pagó (con su descuento). El dinero sale del cajón del turno de quien procesa la devolución; si la venta fue a crédito, esa parte baja la deuda del cliente en lugar de sacar efectivo.
- **Reimprimir un ticket:** *Ventas →* abre la venta → *Ver / reimprimir ticket*. El cajero solo ve sus propias ventas.

---

## 6. Inventario y compras

### Compras
*Compras → Nueva compra:* elige proveedor, factura y fecha; escanea la mercancía y captura cantidad y costo (sin impuestos). Al registrar: sube la existencia, se guarda el último costo y el costo promedio, y se actualiza el costo vigente según el método elegido en Configuración. Si te equivocas, *Cancelar compra* (con motivo) regresa las existencias.

### Sugerencia de compra
*Compras → Sugerencia de compra* (requiere `compras.ver`; **Crear compra** requiere `compras.registrar`). Por cada artículo activo con inventario calcula:
- **Promedio diario** = (vendido − devuelto) de ventas completadas en los últimos N días (7, 15, 30, 60 o 90; por defecto 30) ÷ N. Las ventas canceladas no cuentan.
- **Objetivo** = promedio × días de cobertura (por defecto 15, de 1 a 90). Nunca es menor que el **mínimo** del artículo y, si el artículo tiene **máximo**, no lo pasa (salvo para llegar al mínimo).
- **Comprar** = objetivo − existencia, redondeado hacia arriba (enteros; con tres decimales en artículos a granel). Solo aparecen los que dan una cantidad mayor a cero.
- **Alcanza** = días que dura la existencia actual al ritmo de venta. Estados: *Agotado*, *Bajo el mínimo* y *Cobertura baja*, en ese orden de prioridad.
- **Costo estimado** con el último costo de compra (o el costo del artículo si nunca se compró).

Se puede filtrar por departamento, **exportar a Excel** o **Crear compra**, que abre *Nueva compra* con las partidas y cantidades sugeridas para revisarlas. Un artículo nuevo, con pocos días a la venta, puede parecer de baja rotación porque el promedio se divide entre todo el periodo.

### Existencias
*Inventario* muestra existencias, los artículos **bajo el mínimo** y **agotados**, y el valor de la mercancía. Cada artículo tiene su **kardex** con todos sus movimientos.

### Ajustes
*Inventario → Ajustes:* para mermas, caducidades, robos o correcciones, siempre con motivo. Merma, caducidad y robo solo pueden ser salidas.

### Conteo físico
*Inventario → Conteo físico:* empieza un conteo, escanea cada artículo y escribe cuántos hay. Verás la diferencia contra el sistema. Al **aplicar**, se ajusta solo la diferencia (las ventas hechas mientras contabas no se pierden). Puedes contar solo una parte de la tienda.

---

## 7. Clientes y crédito

*Clientes:* alta de clientes con teléfono, lista de precios (1, 2 o 3) y **límite de crédito** (0 = no se le fía; solo quien tiene el permiso lo cambia).

- **Vender a crédito:** elige al cliente con **F7** y en el cobro agrega «A crédito». No puede pasar de su crédito disponible (límite − lo que ya debe).
- **Abonos:** en el estado de cuenta del cliente, *Registrar abono* (efectivo, tarjeta o transferencia). Un abono en efectivo entra al cajón de tu turno y aparece en tu corte.
- **Estado de cuenta:** todos los movimientos con el saldo; se puede imprimir.

---

## 8. Reportes

*Reportes* (Administrador y Encargado): resumen, por periodo (día, semana, mes), por departamento, por artículo, por cajero, por forma de pago y valor del inventario. Todos se pueden **exportar a Excel**.

- **Utilidad bruta** = precio de venta sin impuestos − costo del artículo *al momento de vender*. Si después cambia el costo, las ventas pasadas no se alteran.
- Las **devoluciones** se restan en el periodo de la venta original.
- El **tablero** (Inicio) muestra lo de hoy contra ayer, los últimos 7 días, lo más vendido, lo que hay que reabastecer, las cajas abiertas y lo que te deben.

---

## 9. Usar el sistema en el teléfono

Todas las pantallas funcionan en teléfono y en computadora. Para **instalarlo como aplicación** abre el sitio en Chrome (Android) o Safari (iPhone) y elige *Agregar a pantalla de inicio*. Requiere internet para operar: sin conexión solo muestra un aviso.

---

## 10. Problemas frecuentes

| Problema | Solución |
|---|---|
| «Abre tu turno de caja antes de vender» | *Mi caja → Abrir turno*. |
| «Esa clave ya está en uso» | Otro artículo (o una clave alterna) ya la tiene. Búscalo en *Artículos*. |
| No puedo cancelar una venta | Su turno ya se cerró o ya tiene devoluciones: usa una devolución. |
| La caja no cuadra | Revisa el corte del turno (entradas, retiros, devoluciones). Pide al encargado ver *Turnos y cortes*. |
| No me aparece una opción del menú | Tu rol no tiene ese permiso. Pídeselo al administrador. |
| Olvidé mi contraseña | El administrador la cambia en *Usuarios*. |
| Se fue el internet | No se puede cobrar. Cuando regrese, el carrito sigue ahí. |
| El ticket sale cortado | *Cajas →* imprime la prueba y revisa el ancho de papel; en el cuadro de impresión pon márgenes en «Ninguno». |

---

## 11. Glosario

- **Arqueo:** contar el dinero del cajón.
- **Corte X / Z:** X es parcial (no cierra); Z es el final del turno.
- **Fondo inicial:** efectivo con el que empieza el turno.
- **Kardex:** historial de movimientos de un artículo.
- **Existencia:** cuántas piezas hay.
- **Costo promedio / último costo:** dos formas de saber cuánto te cuesta un artículo.
- **Margen:** cuánto ganas sobre el costo, en porcentaje.
- **Utilidad bruta:** lo que ganas antes de gastos (renta, sueldos, luz).
- **Granel:** artículo que se vende por peso o medida.
- **Ticket:** comprobante de la venta.
