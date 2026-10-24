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

## Pendientes por decidir

- [ ] **Método de costeo (bloquea Fase 2):** definir si el costo de los artículos se maneja como **último costo** o como **costo promedio**. Afecta la recepción de compras, el valor del inventario y la utilidad en reportes. Claude Code debe recordarlo y preguntar antes de implementar la Fase 2.
  - Estado: consultado con el dueño de la tienda, en espera de su respuesta.
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

- [ ] Departamentos / categorías
- [ ] Unidades de medida (pieza, kg, litro, caja…)
- [ ] Impuestos (IVA 16%, IVA 0%, exento, IEPS) configurables por artículo
- [ ] Artículos: clave principal, **claves alternas** (tabla aparte, varios códigos por artículo, únicos globalmente), descripción, departamento, unidad, costo, precio de venta (precio 1 y opcional precio 2/3 por mayoreo), margen de utilidad, inventariable sí/no, granel sí/no, existencia mínima y máxima, imagen opcional, activo/inactivo
- [ ] Cálculo automático: al cambiar costo o margen se sugiere el precio (y viceversa)
- [ ] Búsqueda rápida por clave, clave alterna o descripción
- [ ] Captura de clave con escáner (el campo recibe el código + Enter)
- [ ] Importación desde Excel con plantilla descargable y reporte de errores por fila
- [ ] Historial de cambios de precio

**Entregable:** catálogo completo administrable, importable desde Excel.

## Fase 2 — Inventario y compras

- [ ] Tabla `movimientos_inventario` (kardex): artículo, tipo, cantidad (+/−), costo, existencia resultante, referencia (venta, compra, ajuste…), usuario, fecha
- [ ] Proveedores
- [ ] Compras / recepción de mercancía: proveedor, folio de factura, partidas (escaneando), actualización de costo según el método definido en "Pendientes por decidir"
- [ ] Ajustes de inventario con motivo (merma, caducidad, robo, corrección)
- [ ] Conteo físico: capturar conteo (escáner o teclado, funcional desde teléfono), mostrar diferencias, aplicar ajuste
- [ ] Kardex por artículo
- [ ] Alerta de artículos bajo mínimo

**Entregable:** el inventario se mueve solo por movimientos trazables; se pueden registrar compras y conteos.

## Fase 3 — Punto de venta (pantalla de caja)

- [ ] Pantalla de venta optimizada para teclado y escáner: campo de captura siempre con foco, Enter agrega el artículo
- [ ] Multiplicador de cantidad (ej. `5*` + código) y captura de peso para granel
- [ ] Búsqueda por descripción (F-key o botón) cuando no hay código
- [ ] Editar cantidad, eliminar partida, descuento por partida y global (con permiso)
- [ ] Artículo no encontrado: aviso claro y, con permiso, alta rápida
- [ ] Cobro: efectivo, tarjeta, transferencia y pago mixto; cálculo de cambio
- [ ] Generación de folio y registro atómico (venta + partidas + movimientos de inventario + pago)
- [ ] Ventas en espera (dejar una venta pendiente y atender a otro cliente)
- [ ] Cancelación de venta con permiso y motivo (regresa inventario)
- [ ] Devoluciones parciales
- [ ] Reimpresión de ticket
- [ ] Atajos de teclado documentados en pantalla
- [ ] Prueba de carga básica: agregar 50 partidas sin degradación perceptible

**Entregable:** se puede vender de punta a punta con escáner.

## Fase 4 — Caja y cortes

- [ ] Cajas / terminales (ej. Caja 1, Caja 2) con su serie de folios
- [ ] Apertura de turno con fondo inicial
- [ ] Entradas y retiros de efectivo con motivo
- [ ] Corte parcial (X) y corte final (Z) por turno: ventas por forma de pago, cancelaciones, devoluciones, retiros, efectivo esperado
- [ ] Arqueo: captura de efectivo contado y diferencia (sobrante/faltante)
- [ ] Impresión del corte
- [ ] Una venta solo puede registrarse con turno abierto

**Entregable:** flujo diario completo: abrir caja, vender, retirar, cortar.

## Fase 5 — Impresión y hardware

- [ ] Ticket de venta en formato 58mm y 80mm vía `window.print()` con CSS de impresión (primera versión)
- [ ] Evaluar QZ Tray para impresión directa ESC/POS sin diálogo y apertura de cajón de dinero
- [ ] Configuración por caja: ancho de papel, impresora, imprimir automáticamente sí/no
- [ ] (Opcional) Lectura de báscula vía Web Serial en Chrome
- [ ] (Opcional) Etiquetas de precio / códigos de barras para artículos sin código

**Entregable:** tickets impresos de forma confiable en la impresora de la tienda.

## Fase 6 — Clientes y crédito (opcional)

- [ ] Catálogo de clientes
- [ ] Venta a crédito con límite
- [ ] Abonos y estado de cuenta
- [ ] Asignar lista de precios (precio 1/2/3) por cliente

## Fase 7 — Reportes y tablero

- [ ] Tablero móvil: ventas del día, ticket promedio, top artículos, artículos bajo mínimo
- [ ] Ventas por periodo, por departamento, por artículo, por cajero, por forma de pago
- [ ] Utilidad bruta (precio de venta − costo guardado en la partida)
- [ ] Valor del inventario
- [ ] Exportación a Excel de todos los reportes
- [ ] Índices en BD revisados para los reportes más usados

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
