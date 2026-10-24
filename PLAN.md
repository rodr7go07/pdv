# Plan de trabajo — Punto de Venta (inspirado en SICAR)

Sistema de punto de venta web para **una tienda** (la tienda de un amigo; él es el usuario final y quien resuelve las dudas de negocio), usable desde PC de caja, tablet y teléfono.
Servidor en la nube (DigitalOcean). Internet de respaldo en la tienda (router con failover 4G/5G) para mitigar caídas.

## Stack

- **Backend:** Laravel (última versión estable), PHP 8.3+
- **UI:** Livewire (última versión estable) + Alpine.js + Tailwind CSS
- **Base de datos:** MySQL 8
- **Roles y permisos:** `spatie/laravel-permission`
- **Importación/exportación Excel:** `maatwebsite/excel` (o `openspout/openspout` si se busca algo más ligero)
- **Pruebas:** Pest
- **PWA:** manifest + service worker propio (instalable en teléfono/tablet; página offline informativa, sin operación offline)
- **Desarrollo local:** Windows + Laragon
- **Producción:** Droplet DigitalOcean + Nginx + Certbot

## Decisiones técnicas clave (aplican a todas las fases)

1. **Dinero:** columnas `DECIMAL(12,2)` para importes y `DECIMAL(12,4)` para costos unitarios. Nunca `float`. Cálculos con `brick/math` o bcmath, redondeo explícito.
2. **Cantidades:** `DECIMAL(12,3)` para soportar artículos a granel (kg, litros).
3. **La existencia no se edita directamente.** Todo cambio de inventario pasa por un **movimiento** (kardex): compra, venta, cancelación, ajuste, conteo físico, devolución. La existencia en `articulos` es un acumulado que se actualiza dentro de la misma transacción que crea el movimiento.
4. **Ventas con "foto" del momento:** cada partida de venta guarda descripción, precio, costo e impuestos tal como estaban al vender. Cambiar el precio de un artículo nunca altera ventas pasadas.
5. **Transacciones y bloqueos:** registrar una venta, compra o ajuste se hace en `DB::transaction()` con `lockForUpdate()` sobre los artículos afectados.
6. **Folios:** consecutivos por serie (ej. serie por caja), generados dentro de la transacción con bloqueo para evitar duplicados.
7. **Nada se borra:** ventas y movimientos se **cancelan** (con motivo, usuario y fecha), no se eliminan. Catálogos con soft deletes.
8. **Auditoría:** registrar quién y cuándo en operaciones sensibles (cancelaciones, cambios de precio, ajustes, retiros de caja).
9. **Lógica de negocio en servicios/acciones** (`app/Actions` o `app/Services`), no en componentes Livewire. Los componentes solo orquestan UI.
10. **Responsive primero:** toda pantalla debe funcionar en teléfono (360px) y en PC.
11. **Acceso:** login por usuario o correo (columna `usuario` única, correo opcional). Sin registro público ni recuperación por correo: el administrador da de alta a los usuarios y restablece contraseñas. Los usuarios tienen `activo`; uno desactivado pierde la sesión de inmediato.
12. **Permisos:** catálogo en el enum `App\Enums\Permiso` y asignación por rol en `App\Enums\Rol`. `RolesYPermisosSeeder` es idempotente y se ejecuta en cada despliegue. Cada fase nueva agrega sus permisos al enum.
13. **Front-end:** Tailwind v4 (tokens en `resources/css/app.css`), fuentes autoalojadas con Fontsource, iconos Phosphor (`codeat3/blade-phosphor-icons`). Identidad visual: rótulo añil + acento rosa mexicano + motivo de ticket térmico.
14. **Caché:** Laravel 13 no deserializa objetos desde la caché; se guardan arreglos de atributos, no modelos.
15. **Precios e impuestos:** el costo se captura **sin** impuestos y los precios de venta (1, 2 y 3) **con** impuestos incluidos, como se maneja al público en México. El margen es la utilidad sobre el costo usando el precio sin impuestos; las tasas de IVA e IEPS se suman. Cálculos en `App\Services\CalculadoraPrecios` con `brick/math`, redondeo a centavos hacia arriba. *Por confirmar con el dueño.*
16. **Claves:** la clave principal y las alternas son únicas en todo el sistema, incluso contra artículos eliminados (la clave queda reservada). Un artículo lleva como máximo un IVA y puede llevar varios IEPS.
17. **Catálogos:** departamentos, unidades e impuestos no se eliminan, se desactivan. Un artículo ligado a un catálogo desactivado sigue siendo editable.
18. **Importación de Excel:** de todo o nada. Si alguna fila tiene error no se guarda ninguna y se reporta fila por fila. Si la clave ya existe se actualiza ese artículo y las celdas vacías conservan el valor actual. Los departamentos nuevos se crean; unidades e impuestos deben existir.
19. **Costeo (provisional):** cada compra guarda el **último costo** y el **costo promedio ponderado** del artículo (el promedio ignora existencia negativa o cero). En Configuración se elige cuál es el *costo vigente* (el de `articulos.costo`, usado para márgenes, utilidad y valor del inventario); por defecto, promedio. Cambiar el método aplica desde la siguiente compra. Cada cambio del costo vigente por compra queda en `historial_precios` con origen `compra`.
20. **Inventario:** `RegistrarMovimientoInventario` es la única vía que cambia `articulos.existencia`; la columna no es asignable en masa. Los artículos no inventariables no generan movimientos. Existencia negativa permitida por defecto (`configuracion.vender_sin_existencia`).
21. **Folios:** tabla `folios` por serie, leída con `lockForUpdate` dentro de la transacción del documento (`App\Services\Folios`). Compras usan la serie `C` (`C-000001`).
22. **Conteo físico:** al contar se guarda lo que decía el sistema; al aplicar se ajusta solo la *diferencia* (contado − sistema al contar), así las ventas hechas durante el conteo no se pierden. Se puede contar solo una parte del inventario.
23. **Cancelar una compra** regresa las existencias (movimiento `cancelacion_compra`) pero no revierte los costos; el siguiente movimiento de compra los recalcula.
24. **Caja (punto de venta):** el carrito vive en el navegador (Alpine) para que escanear sea instantáneo; al cobrar, el servidor vuelve a leer precios, impuestos y existencias de la base (con los artículos bloqueados) y recalcula todo. Nunca se confía en los precios que manda la pantalla. El dinero en el navegador se maneja en enteros (centavos) para coincidir con el servidor.
25. **Descuentos:** por partida y general, por porcentaje o monto, siempre con permiso `ventas.descuento` (verificado en el servidor). El descuento general se reparte entre las partidas en proporción a su importe y la última absorbe el redondeo. Una venta no puede quedar en $0.
26. **Pagos:** efectivo, tarjeta y transferencia, solos o mezclados. Tarjeta y transferencia se cobran exactos; solo el efectivo genera cambio. En `venta_pagos` el efectivo se guarda por lo aplicado a la venta; lo recibido y el cambio van en `ventas`.
27. **Cancelar una venta:** requiere `ventas.cancelar` y motivo; regresa el inventario; no se permite si la venta ya tiene devoluciones. La venta no se borra (queda con quién, cuándo y por qué).
28. **Devoluciones:** parciales por partida; reembolsan lo realmente cobrado (precio con su descuento); la última pieza absorbe el redondeo para que el total devuelto nunca pase de lo cobrado. El dinero se regresa en efectivo y las piezas vuelven al inventario.
29. **Ticket:** HTML con CSS de impresión para 58 u 80 mm según la caja (`window.print()`); lo reimprime su cajero o quien tenga `ventas.ver_todas`. Las ventas en espera se guardan en base de datos por caja (máximo 30).
30. **Turnos:** solo se vende con el turno propio abierto en la caja (`turnos`). La base de datos garantiza un turno abierto por caja y por usuario con dos columnas únicas (`caja_abierta_id`, `usuario_abierto_id`) que valen `NULL` al cerrar. La caja que usa el punto de venta sale del turno abierto del cajero.
31. **Efectivo esperado** = fondo inicial + efectivo cobrado en ventas + entradas − retiros − devoluciones. Tarjeta y transferencia no entran al cajón. Las ventas canceladas no cuentan. Los retiros no pueden exceder el efectivo esperado.
32. **Cancelación vs. devolución:** una venta solo se cancela mientras su turno sigue abierto (así ningún corte cerrado cambia); después se usa una devolución. Una devolución sale del cajón del turno de quien la procesa (o, si no tiene turno, del turno aún abierto de la venta); sin ningún turno abierto no se puede devolver dinero.
33. **Cortes:** el corte X (parcial) guarda una foto de las cifras sin cerrar; el Z (final) pide el efectivo contado (arqueo), calcula sobrante o faltante, guarda la foto y cierra el turno. Los cortes guardados no cambian aunque después se registren más movimientos. El cajero no ve el efectivo esperado (corte "ciego"); solo quien tiene `caja.supervisar`.
34. **Cajas:** la serie de una caja con ventas no se puede cambiar y una caja con turno abierto no se desactiva. Cada caja define el ancho de papel (58 u 80 mm) y si imprime sola.
35. **Impresión:** tickets y cortes se imprimen con HTML y CSS de impresión del navegador (58 u 80 mm según la caja). Para imprimir sin diálogo se recomienda abrir la caja con Chrome `--kiosk-printing` y la impresora de tickets como predeterminada. QZ Tray (ESC/POS directo, necesario solo para abrir el cajón) quedó **evaluado, no integrado**; el análisis y la recomendación están en `docs/impresion.md`. Por eso no se guardan por caja el nombre de la impresora ni «abrir cajón»: hoy no se usarían.
36. **Hardware opcional:** las etiquetas de precio (EAN-13 o Code 128 con JsBarcode, rollo 50×30 mm o carta) están listas. La lectura de báscula por Web Serial es **experimental y no se probó con una báscula real**.
37. **Crédito:** el saldo del cliente (`clientes.saldo`) solo cambia mediante `RegistrarMovimientoCliente` (cargo, abono, cancelación, devolución), con el cliente bloqueado y el saldo resultante guardado en cada movimiento. El saldo nunca queda negativo. Una venta puede pagarse parcial o totalmente a crédito si el cliente está activo y tiene límite y disponible suficientes; lo fiado no entra al cajón ni al efectivo esperado.
38. **Lista de precios por cliente:** al elegir un cliente en la caja, sus precios (1, 2 o 3) mandan sobre los demás; si el artículo no tiene ese precio se usa el precio 1. Solo quien tiene `clientes.credito` fija el límite de crédito y la lista de precios.
39. **Abonos:** requieren `clientes.abonar`. Un abono en efectivo exige turno abierto de quien lo recibe y aparece en su corte (suma al efectivo esperado); un abono con tarjeta o transferencia no. No se puede abonar más de lo que se debe.
40. **Cancelar o devolver una venta a crédito:** la parte fiada baja la deuda en lugar de mover efectivo. En una devolución esa parte es proporcional a lo fiado y nunca pasa de lo que el cliente todavía debe ni de lo fiado pendiente de devolver; el resto sale en efectivo. Si el cliente ya había abonado, la cancelación solo baja lo que todavía debe.
41. **Pagos de una venta** se listan en el orden en que se guardaron (`venta_pagos.id`). Las ventas en espera no conservan el cliente: se vuelve a elegir al recuperarlas.
42. **Reportes:** `App\Services\ReporteVentas` concentra las cifras. Solo cuentan ventas completadas; la **utilidad bruta** es el precio de venta sin impuestos menos el costo guardado en cada partida (no el costo actual); las devoluciones se restan en el periodo de la venta original, con su costo e impuestos proporcionales. El departamento que se reporta es el actual del artículo. El «valor del inventario» usa el costo vigente y solo artículos con inventario y existencia mayor que cero.
43. **Tablero:** quien tiene `reportes.ver` ve ventas de hoy contra ayer, ticket promedio, utilidad, últimos 7 días, lo más vendido, artículos por reabastecer, cajas abiertas y cuentas por cobrar (se actualiza solo cada 60 s). El cajero ve solo su turno. Todas las pantallas de reportes se exportan a Excel con los mismos filtros.
44. **Índices revisados (Fase 7):** con 4,000 ventas y 12,000 partidas en MySQL, las consultas de reportes tardan entre 13 y 70 ms y `EXPLAIN` muestra `ventas_estado_created_at_index` y el índice de la llave foránea de `venta_partidas`. No hizo falta agregar índices; conviene repetir la medición si el volumen supera unas 100,000 ventas.

## Pendientes por decidir

- [x] **Método de costeo (bloqueaba Fase 2) — resuelto de forma PROVISIONAL, por confirmar con el dueño:** definir si el costo de los artículos se maneja como **último costo** o como **costo promedio**. Afecta la recepción de compras, el valor del inventario y la utilidad en reportes. Claude Code debe recordarlo y preguntar antes de implementar la Fase 2.
  - Estado: el dueño aún no responde. Se implementó la opción propuesta abajo para no frenar el desarrollo; cambiar de método es solo un ajuste en Configuración y no pierde datos (ver decisión 19).
  - Opción a considerar: guardar ambos costos en cada compra (último y promedio) y definir cuál se usa para sugerir el precio de venta y cuál para utilidad y valor del inventario.

---

## Fase 0 — Fundamentos

- [x] Crear proyecto Laravel, configurar `.env` para Laragon, repositorio git
- [x] Instalar Livewire, Tailwind, Alpine, Pest
- [x] Autenticación (login por usuario/correo y contraseña)
- [x] Roles iniciales: **Administrador**, **Encargado**, **Cajero**, con permisos granulares (ej. `ventas.cancelar`, `articulos.editar_precio`, `caja.retiro`)
- [x] Layout base responsive: menú lateral en PC, menú inferior o hamburguesa en móvil
- [x] Configuración general de la tienda (nombre, RFC, dirección, leyenda de ticket, logo) en tabla `configuracion`
- [x] PWA: `manifest.webmanifest`, íconos, service worker que cachea assets estáticos y muestra página "Sin conexión"
- [x] Seeders: usuario admin, roles y permisos

**Entregable:** se puede iniciar sesión desde PC y teléfono, instalar la app en el teléfono y ver el menú según el rol.

## Fase 1 — Catálogo de artículos

- [x] Departamentos / categorías
- [x] Unidades de medida (pieza, kg, litro, caja…)
- [x] Impuestos (IVA 16%, IVA 0%, exento, IEPS) configurables por artículo
- [x] Artículos: clave principal, **claves alternas** (tabla aparte, varios códigos por artículo, únicos globalmente), descripción, departamento, unidad, costo, precio de venta (precio 1 y opcional precio 2/3 por mayoreo), margen de utilidad, inventariable sí/no, granel sí/no, existencia mínima y máxima, imagen opcional, activo/inactivo
- [x] Cálculo automático: al cambiar costo o margen se sugiere el precio (y viceversa)
- [x] Búsqueda rápida por clave, clave alterna o descripción
- [x] Captura de clave con escáner (el campo recibe el código + Enter)
- [x] Importación desde Excel con plantilla descargable y reporte de errores por fila
- [x] Historial de cambios de precio

**Entregable:** catálogo completo administrable, importable desde Excel.

## Fase 2 — Inventario y compras

- [x] Tabla `movimientos_inventario` (kardex): artículo, tipo, cantidad (+/−), costo, existencia resultante, referencia (venta, compra, ajuste…), usuario, fecha
- [x] Proveedores
- [x] Compras / recepción de mercancía: proveedor, folio de factura, partidas (escaneando), actualización de costo según el método definido en "Pendientes por decidir"
- [x] Ajustes de inventario con motivo (merma, caducidad, robo, corrección)
- [x] Conteo físico: capturar conteo (escáner o teclado, funcional desde teléfono), mostrar diferencias, aplicar ajuste
- [x] Kardex por artículo
- [x] Alerta de artículos bajo mínimo

**Entregable:** el inventario se mueve solo por movimientos trazables; se pueden registrar compras y conteos.

## Fase 3 — Punto de venta (pantalla de caja)

- [x] Pantalla de venta optimizada para teclado y escáner: campo de captura siempre con foco, Enter agrega el artículo
- [x] Multiplicador de cantidad (ej. `5*` + código) y captura de peso para granel
- [x] Búsqueda por descripción (F-key o botón) cuando no hay código
- [x] Editar cantidad, eliminar partida, descuento por partida y global (con permiso)
- [x] Artículo no encontrado: aviso claro y, con permiso, alta rápida
- [x] Cobro: efectivo, tarjeta, transferencia y pago mixto; cálculo de cambio
- [x] Generación de folio y registro atómico (venta + partidas + movimientos de inventario + pago)
- [x] Ventas en espera (dejar una venta pendiente y atender a otro cliente)
- [x] Cancelación de venta con permiso y motivo (regresa inventario)
- [x] Devoluciones parciales
- [x] Reimpresión de ticket
- [x] Atajos de teclado documentados en pantalla
- [x] Prueba de carga básica: agregar 50 partidas sin degradación perceptible

**Entregable:** se puede vender de punta a punta con escáner.

## Fase 4 — Caja y cortes

- [x] Cajas / terminales (ej. Caja 1, Caja 2) con su serie de folios
- [x] Apertura de turno con fondo inicial
- [x] Entradas y retiros de efectivo con motivo
- [x] Corte parcial (X) y corte final (Z) por turno: ventas por forma de pago, cancelaciones, devoluciones, retiros, efectivo esperado
- [x] Arqueo: captura de efectivo contado y diferencia (sobrante/faltante)
- [x] Impresión del corte
- [x] Una venta solo puede registrarse con turno abierto

**Entregable:** flujo diario completo: abrir caja, vender, retirar, cortar.

## Fase 5 — Impresión y hardware

- [x] Ticket de venta en formato 58mm y 80mm vía `window.print()` con CSS de impresión (primera versión)
- [x] Evaluar QZ Tray para impresión directa ESC/POS sin diálogo y apertura de cajón de dinero — evaluado en `docs/impresion.md` (no integrado)
- [x] Configuración por caja: ancho de papel, impresora, imprimir automáticamente sí/no
- [x] (Opcional) Lectura de báscula vía Web Serial en Chrome — **experimental, sin probar con una báscula real**
- [x] (Opcional) Etiquetas de precio / códigos de barras para artículos sin código

**Entregable:** tickets impresos de forma confiable en la impresora de la tienda.

## Fase 6 — Clientes y crédito (opcional)

- [x] Catálogo de clientes
- [x] Venta a crédito con límite
- [x] Abonos y estado de cuenta
- [x] Asignar lista de precios (precio 1/2/3) por cliente

## Fase 7 — Reportes y tablero

- [x] Tablero móvil: ventas del día, ticket promedio, top artículos, artículos bajo mínimo
- [x] Ventas por periodo, por departamento, por artículo, por cajero, por forma de pago
- [x] Utilidad bruta (precio de venta − costo guardado en la partida)
- [x] Valor del inventario
- [x] Exportación a Excel de todos los reportes
- [x] Índices en BD revisados para los reportes más usados

## Fase 8 — Despliegue en DigitalOcean

- [ ] Droplet (Ubuntu LTS), usuario no root, firewall (UFW), SSH por llave
- [ ] PHP-FPM, Nginx, MySQL (en el droplet o cluster gestionado — decidir por costo)
- [ ] Certbot SSL y dominio
- [ ] Supervisor para colas y cron para el scheduler
- [ ] Script de despliegue (git pull, composer, migraciones, build de assets, cachés, reinicio de colas)
- [ ] Respaldos automáticos diarios de la base de datos a DigitalOcean Spaces (con retención) y prueba de restauración
- [ ] Monitoreo básico (uptime y errores en logs)
- [ ] Zona horaria `America/Mexico_City` en app y base de datos

## Fase 9 — Futuro

- [ ] Facturación CFDI 4.0 vía PAC (factura individual y global)
- [ ] Multi-sucursal
- [ ] Operación offline real en la caja (solo si las caídas de internet siguen siendo un problema)
