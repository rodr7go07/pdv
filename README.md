# Punto de Venta

Sistema de punto de venta web para **una tienda de abarrotes**: caja con escáner, inventario con kardex, compras, turnos y cortes, clientes con crédito, reportes y exportación a Excel. Se usa desde la computadora de la caja, una tablet o el teléfono.

> Escrito en español (interfaz, base de datos y comentarios). La guía para el personal está dentro de la aplicación, en **Guía de uso**.

## Qué incluye

| Área | Qué hace |
|---|---|
| **Artículos** | Catálogo con claves alternas, impuestos (IVA/IEPS), precios 1–3, costo/margen enlazados, historial de precios, importación desde Excel, etiquetas con código de barras y foto desde el teléfono |
| **Inventario y compras** | Existencia que solo cambia por movimientos (kardex), compras con costo promedio y último costo, ajustes con motivo, conteo físico, alertas de mínimos y sugerencia de compra según lo vendido |
| **Caja** | Pantalla de venta para lector, teclado y cámara del teléfono, granel, descuentos, pagos mixtos, ventas en espera, tickets de 58/80 mm, cancelaciones y devoluciones |
| **Turnos y cortes** | Fondo inicial, entradas y retiros, corte X y Z con arqueo, cajas con serie de folios |
| **Clientes** | Crédito con límite, abonos, estado de cuenta, lista de precios por cliente |
| **Escaneos** | Bitácora de todos los códigos de barras escaneados (lector o cámara), con alta rápida de los que no existen |
| **Reportes** | Tablero del día, ventas por periodo/departamento/artículo/cajero/forma de pago, utilidad bruta, valor del inventario, Excel |
| **Administración** | Usuarios y roles (Administrador, Encargado, Cajero), configuración de la tienda, PWA instalable |

## Tecnología

Laravel 13 · PHP 8.3 · Livewire 3 + Volt · Alpine.js · Tailwind CSS 4 · MySQL 8 · Spatie Permission · Maatwebsite Excel · Pest. Dinero con `brick/math` (nunca `float`).

## Instalación local (Windows + Laragon)

Requisitos: Laragon con PHP 8.3 y MySQL 8, Composer y Node 22.

```powershell
git clone https://github.com/rodr7go07/pdv.git punto-de-venta
cd punto-de-venta
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Crea la base de datos `punto_venta` (utf8mb4) en MySQL, revisa `DB_*` en `.env` y ejecuta:

```powershell
php artisan migrate --seed          # roles, catálogos base, caja inicial y usuarios de prueba (solo en entorno local)
php artisan db:seed --class=DemoSeeder   # opcional: artículos, proveedor y compra de demostración
npm run build                       # o `npm run dev` mientras desarrollas
php artisan storage:link
```

Abre `http://punto-de-venta.test` (Laragon → Reload) o ejecuta `php artisan serve`. Los usuarios de prueba y su contraseña local están en `database/seeders/DatabaseSeeder.php`; **en producción la contraseña del administrador viene de `ADMIN_PASSWORD`**.

> Si en tu terminal `php -v` muestra una versión menor a 8.3, usa el PHP 8.3 de Laragon en esa sesión: `$env:Path = 'C:\laragon\bin\php\php-8.3.8-nts-Win32-vs16-x64;' + $env:Path`.

## Comandos útiles

```powershell
php artisan test                    # todas las pruebas (SQLite en memoria; no tocan tu base MySQL)
php vendor\bin\pint                 # formato de código
php artisan db:seed --class=RolesYPermisosSeeder   # sincronizar permisos nuevos (idempotente)
```

## Estructura

```
app/Actions/      Lógica de negocio (ventas, compras, caja, inventario, clientes…)
app/Services/     Cálculos puros (precios, importes de venta, reportes, folios, resumen de turno)
app/Enums/        Permisos, roles y catálogos fijos
resources/views/livewire/   Pantallas (Livewire Volt); los componentes solo orquestan la interfaz
resources/js/     Caja (pos.js), cámara y escáner de códigos (camara.js, escaner.js), foto del artículo (foto.js),
                  instalación de la app (pwa.js), etiquetas y registro del service worker
deploy/           Manual y scripts para DigitalOcean (Nginx, Supervisor, cron, respaldos)
docs/             Guía detallada, impresión y hardware
PLAN.md           Fases, tareas y decisiones técnicas
```

## Documentación

- [`PLAN.md`](PLAN.md): fases, estado y las decisiones técnicas numeradas.
- [`docs/GUIA_DETALLADA.md`](docs/GUIA_DETALLADA.md): manual completo de uso.
- [`docs/impresion.md`](docs/impresion.md): impresión de tickets, QZ Tray, báscula y etiquetas.
- [`deploy/README.md`](deploy/README.md): puesta en producción paso a paso.

## Estado

El estado de las fases, las tareas y los pendientes con el dueño de la tienda está en [`PLAN.md`](PLAN.md); esa es la única fuente. En resumen: las Fases 0 a 7 están terminadas, la Fase 8 (despliegue) está preparada pero sin ejecutar en un servidor real, y la Fase 9 sigue como trabajo futuro.
