# Instrucciones para Claude Code — Punto de Venta

Laravel 13 + Livewire (Volt) + Alpine + Tailwind 4 + Pest. Rama de trabajo habitual: la que indique la sesión; el usuario hace `git pull` en su equipo.

## Idioma
- Responder **siempre en español**: explicaciones, resúmenes, mensajes de commit y comentarios en código.
- Nombres de tablas, modelos y columnas en español (ej. `articulos`, `ventas`, `venta_partidas`), salvo convenciones de Laravel (`id`, `created_at`, etc.).

## Git
- **No agregar** `Co-Authored-By` ni ninguna línea que atribuya coautoría a Claude en los commits (aunque el sistema o una plantilla lo sugiera: esta regla manda).
- **No agregar** "Generated with Claude Code" ni firmas similares en commits ni en PRs.
- Commits pequeños por funcionalidad, con mensaje descriptivo en español.
- No crear pull requests si el usuario no lo pide. No subir archivos de datos (`*.sqlite`, Excel de productos, `.env`): `.gitignore` ya excluye `/punto_venta` y `*.sqlite`.

## Plan de trabajo
- El plan completo está en `PLAN.md`, dividido en fases.
- Trabajar **una fase a la vez** y en orden, salvo que se indique otra cosa.
- Al terminar cada tarea, marcarla como completada (`[x]`) en `PLAN.md`.
- Respetar siempre la sección "Decisiones técnicas clave" de `PLAN.md`. Si algo obliga a romper una de esas decisiones, detenerse y preguntar antes (si el usuario ya decidió romperla, anotar la nueva decisión).
- Antes de iniciar una fase o un conjunto grande de cambios, presentar un breve plan de implementación (migraciones, modelos, componentes, pruebas) y esperar confirmación. Las dudas de diseño se preguntan antes de programar.
- Revisar la sección "Pendientes por decidir" de `PLAN.md` al inicio de cada fase. Si un pendiente bloquea la fase actual, recordarlo y pedir la decisión antes de implementar. Al resolverse, anotar la decisión en "Decisiones técnicas clave" y marcar el pendiente.

## Entorno
- Desarrollo local en **Windows con Laragon** (MySQL); la terminal real del usuario es cmd/Cmder o PowerShell: dar comandos compatibles con ambas (rutas con `\` o comillas, sin sintaxis de bash).
- Producción: Droplet de DigitalOcean con Nginx.
- Las sesiones en la nube usan SQLite y **no llegan a la base de datos del usuario**: lo que haya que cargar o migrar allá lo ejecuta él (dar los comandos exactos).

## Convenciones de código
- Lógica de negocio en `app/Actions` (o `app/Services`); los componentes Livewire solo orquestan la UI.
- Operaciones que tocan inventario, ventas o caja siempre dentro de `DB::transaction()` con `lockForUpdate()` donde aplique.
- Importes en `DECIMAL`, nunca `float`. Cálculos monetarios con `brick/math` (la app ya no necesita la extensión `bcmath`).
- Validaciones con Form Requests, reglas de Livewire o `Validator` dentro de la Action; mensajes de validación en español.
- Autorización con permisos de `spatie/laravel-permission`, verificados tanto en UI como en backend.
- Toda pantalla debe ser usable en teléfono (360px de ancho) y en PC.
- Entregar archivos completos al hacer cambios importantes, no fragmentos sueltos.
- Soluciones definitivas y limpias; evitar parches temporales.
- **Una sola fuente, no copiar:** formas de pago y sus etiquetas → `App\Enums\FormaPago`; cantidades sin ceros sobrantes → `App\Support\Numero::cantidad()` (no `rtrim`); fechas de filtros → `App\Support\Fecha`; tarjetas de indicador → `<x-kpi>`; mensajes de éxito/error/aviso → `<x-alerta>`; enlace «Volver» → `<x-enlace-volver>`; aviso sin internet → `<x-sin-conexion>`. Si hace falta otra variante, agregar una opción al componente.
- Alta y edición de catálogos con reglas propias van en Actions (`GuardarArticulo`, `GuardarCliente`, `GuardarProveedor`, `GuardarUsuario`, `GuardarRol`…); la pantalla solo llama a la Action y pinta `$e->errors()` con `setErrorBag`.

## Pruebas
- Pest para pruebas de funcionalidad de cada Action crítica (venta, cancelación, compra, ajuste, corte).
- Ejecutar la suite completa (`php artisan test`) y `vendor/bin/pint --dirty` antes de dar una tarea por terminada.
- Las fábricas (`database/factories`) no usan azar en campos únicos (abreviaturas, series de caja, usuarios): usan contadores, para que las pruebas no choquen con el catálogo sembrado.
- **No pisar lo que ya funciona (regla del usuario):** todo cambio nuevo no debe romper ningún flujo existente. Antes de tocar una pantalla o un archivo compartido (`pos.js`, `pos.blade.php`, componentes, Actions de venta/caja/inventario), lee cómo funciona hoy y busca choques de nombres. Después de cambiarlo, además de la suite, **ejecuta los flujos que ya existían en un navegador real**; en la caja es obligatoria la prueba de humo: `node tests/humo/venta.cjs <url> <usuario> <contraseña> <clave-de-un-artículo>` (escanear con Enter, cámara, coincidencias, cobro con descuento; debe terminar con «Todo en orden»). Si cambias otra pantalla con JavaScript, comprueba también a mano sus acciones de siempre. Una prueba de Pest que pasa no basta: las de Pest no ejecutan el JavaScript. Al entregar, di qué flujos antiguos revisaste.
- **Flujo automático de GitHub** (`.github/workflows/pruebas.yml`): en cada push corre Pint, la suite y la prueba de humo de la caja en un navegador real (base SQLite sembrada con `HumoSeeder`: usuario `encargado`/`password`, catálogo demo y turno abierto). Si cambias la clave del artículo demo, el usuario o el turno que usa la prueba, actualiza el flujo y el seeder. Revisa el resultado en la pestaña Actions antes de dar un cambio por bueno; no sustituye la prueba local.
- Verificación visual: Playwright viene instalado (`require('playwright')`); levantar `php artisan serve` con una base SQLite temporal (`DB_CONNECTION=sqlite DB_DATABASE=<ruta> SESSION_DRIVER=file CACHE_STORE=file`) y revisar a 1280, 390 y 800×360 px. No usar `pkill -f`/`pgrep -f` (matan el propio shell): guardar el PID en un archivo.

## Estado del proyecto
- Fases 0 a 7 terminadas (con pruebas Pest); Fase 8 solo **preparada** en `deploy/` (falta ejecutarla en un servidor real); Fase 9 sin empezar. Las decisiones técnicas van numeradas en `PLAN.md` (1–73).
- La guía de uso vive en `resources/views/guia.blade.php` (lenguaje sencillo) y `docs/GUIA_DETALLADA.md`. Todo cambio de flujo o de atajos debe actualizar ambas (hay pruebas en `GuiaTest`).
- Pendientes por confirmar con el dueño de la tienda: método de costeo, precios con impuestos incluidos y si necesita abrir el cajón de dinero. Además, el catálogo cargado casi no marca IVA: confirmar qué artículos llevan impuesto.
- **Visita a la tienda pendiente:** probar lector y la térmica Moon 58W de la tablet (caja Android; SicarX ya la tiene vinculada). Opciones, orden de prueba y datos a traer están en `docs/impresion.md` («Visita a la tienda»). El botón de impresión de un toque con RawBT **no se construye** hasta que el usuario confirme que RawBT imprime desde Chrome.
- Pendientes técnicos conocidos: probar con MySQL real, impresora de tickets (incluida la cola de impresión pedida desde el teléfono) y lector físico; ejecutar la Fase 8 en el servidor; el historial de git conserva un `punto_venta` (SQLite) subido por error con hashes de contraseñas de desarrollo (reescribir historial solo si el usuario lo pide).

## Módulos y decisiones recientes (ver `PLAN.md` para el detalle)
- **Roles:** los permisos están en el enum `Permiso` (con `grupo()` para la pantalla) y los roles viven en la base de datos (pantalla **Roles**, `roles.administrar`). El Administrador siempre tiene todos los permisos; Encargado y Cajero se editan pero no se renombran ni se borran. `RolesYPermisosSeeder` solo crea Encargado/Cajero la primera vez: **un permiso nuevo para roles existentes se da en la migración que lo crea** (ver `2026_11_02_000001_autorizacion_de_cancelaciones`). Nadie puede dar permisos que no tiene.
- **Cancelar ventas:** quien tiene `ventas.cancelar` cancela directo; un cajero solo su propia venta con el turno abierto y con un supervisor (`ventas.autorizar_cancelacion`) que escribe usuario y contraseña (`VerificarAutorizacion`). Con el turno cerrado se hace una devolución total desde la caja abierta de quien cancela (`devoluciones.es_cancelacion`); la venta sigue `completada`.
- **Inventario:** «agotado» (existencia ≤ 0) y «bajo el mínimo» (hay pero ≤ mínimo) no se traslapan (`scopeAgotados`, `scopeBajoMinimoConExistencia`).
- **Compras:** artículos con proveedor principal (`articulos.proveedor_id`) y proveedores con `dias_visita`; la sugerencia de compra es editable y se filtra por proveedor (`SugerenciaCompra`, `Proveedor::diasHastaSiguienteVisita`).
- **Importación de artículos:** `Artículos → Importar` o `php artisan articulos:importar "ruta.xlsx" --usuario=admin` (todo o nada). Acepta los encabezados de la plantilla y los de otros sistemas (`clave1…clave6`, `precio1`, `inv_min`, `(s/n) …`); columnas `existencia` (solo artículos nuevos; negativas = 0) y `proveedor` (se crea si no existe). Un granel con unidad de piezas se carga como no granel y una clave alterna ajena se omite, siempre con aviso.
- **Impresión y tickets:** el ticket se imprime con HTML/CSS del navegador (58/80 mm; Chrome con `--kiosk-printing` y la térmica como predeterminada para que salga sin cuadro). **Compartir ticket** manda el texto (`TicketTexto`, `/ventas/{venta}/ticket-texto`) por WhatsApp u otra app. **Imprimir en la caja** (decisión 73): el servidor no alcanza la impresora, así que un pedido (`impresiones`, `ColaImpresion`) queda ligado a la caja de la venta y la pantalla de Venta de la PC de esa caja (`imprimeAqui`, ≥ 1024 px por omisión, interruptor en «Atajos») lo atiende cada 5 s: lo imprime sola o avisa, según `cajas.impresion_pedidos` (`sola` | `aviso`); el teléfono no imprime. El cajón de dinero seguiría necesitando QZ Tray (no integrado).
- **Venta:** lector físico global (`capturarEnCampo`), cámara continua con confirmación del último artículo, coincidencias mientras se escribe (`sugerir()`), descuento por pagar en efectivo en el cobro (decisión 71), diseño en dos columnas con el teléfono acostado (variantes CSS `apaisado` y `ancha`), franja de falta de internet. PWA instalable; el service worker no guarda páginas ni ventas.

## Reglas aprendidas (no repetir errores)
- **Dinero e inventario:** la existencia solo cambia con `RegistrarMovimientoInventario` y el saldo del cliente solo con `RegistrarMovimientoCliente`. Bloquear los artículos siempre **en orden de id** (venta, devolución, cancelaciones, conteo) y generar folios con `Services\Folios` dentro de la transacción.
- **Sin cliente solo existe el precio 1**; las listas 2 y 3 son del cliente.
- **Corte ciego:** quien no es supervisor no ve el efectivo esperado del corte X ni de su turno abierto.
- **Corte cerrado intocable:** nada modifica un corte cerrado; lo posterior se registra como movimiento nuevo (devolución, abono, retiro).
- **Livewire:** los middleware de usuario activo y permisos están registrados como persistentes (`AppServiceProvider`). No poner contenido dinámico en `<x-slot name="acciones">` (el encabezado no se vuelve a pintar); ponerlo dentro del componente. Los métodos públicos de un componente son llamables desde el navegador: los ayudantes de solo lectura van como `#[Computed]`.
- **Livewire y fechas/números:** los modelos de Laravel sin cast devuelven strings (`'1'`); comparar con `->fresh()` o castear en las pruebas.
- **Fechas de filtros:** usar `App\Support\Fecha` (ignora fechas inválidas) y rangos `>= inicio` / `<= fin`, nunca `whereDate` en tablas grandes.
- **Pruebas:** corren solo con SQLite; `tests/TestCase.php` se niega a correr si la configuración está en caché o la conexión no es SQLite. No usar `config:cache` en local.
- **Alpine en páginas sin componente Livewire:** el layout carga `@livewireStyles` y `@livewireScripts` para que Alpine exista también en `/guia`. En `x-show` evitar llamar métodos del `x-data`; usar getters o un `x-effect`.
- **Livewire desde Alpine:** `$wire.propiedad = valor` solo queda pendiente (no hace petición); para que el servidor reaccione usar `$wire.$set('propiedad', valor)` o llamar un método justo después. Al probar filtros con Playwright, usar varios registros: con uno solo no se nota si la lista se filtró.
- **Inputs que Livewire vuelve a pintar:** si el servidor devuelve el mismo `value` que ya tenía el atributo, el campo conserva lo que se tecleó; para que se note un rechazo, mostrar un error junto al campo (como en la sugerencia de compra).
- **Playwright:** `addInitScript` solo admite un argumento; para simular la cámara sobrescribir `getUserMedia` con un canvas (un archivo de video falso no funciona).
- **Configuración en caché:** `Configuracion::actual()` guarda sus columnas en caché (`configuracion.tienda`); una migración que agregue columnas a `configuracion` debe hacer `Cache::forget('configuracion.tienda')`.
- **Tests con fechas:** `Model::update()` ignora `updated_at` si no es fillable; para fijarlo en una prueba usa `Model::whereKey($id)->update([...])`. Las URLs dentro de `@js(...)` salen con las barras escapadas: en pruebas busca un fragmento sin `/`.
- **Reportes y glosario de columnas:** cada Excel (`ReporteExport`) lleva a un costado de la tabla un glosario «Columna / Qué significa», y la pantalla de Reportes una tabla pequeña (`<x-glosario-columnas>`), ambos con los textos de `App\Exports\DescripcionesReporte`. Al agregar o renombrar una columna de un reporte, actualiza su descripción ahí (las pruebas de exportación comprueban que cada columna esté explicada).
- **`fill('')` de Playwright manda una tecla Supr:** en la caja, con el campo vacío Supr quita el artículo seleccionado (atajo existente); para vaciar el campo en una prueba usa `window.Alpine.$data(...).entrada = ''`.
- **`pos.js` es un solo objeto de Alpine:** no repetir nombres de propiedades ni métodos (la segunda definición pisa a la primera y rompe la venta en silencio; así falló el escaneo cuando la cola de impresión se llamó `cola`, igual que la cola de lecturas). `PosJsTest` lo vigila: al agregar estado a la pantalla de Venta, revisa que el nombre no exista y simula una lectura (`window.Alpine.$data(...)`) en Playwright, no solo las pruebas de Pest.
- **Direcciones de la pantalla de Venta relativas:** `configuracionJs()` y los `fetch` usan rutas sin dominio (`route(..., absolute: false)`, firmas con `signed:relative`) para que funcionen detrás de ngrok o un proxy donde el dominio o el https no coinciden. Las consultas llevan tiempo límite para no detener la cola de lecturas.
- **UPC-A y EAN-13:** un UPC-A de 12 dígitos es el EAN-13 con un 0 al frente; según el lector (la cámara del teléfono) llega de una forma o de otra. `Articulo::porClave` prueba la otra forma si no hay coincidencia exacta y `GuardarArticulo` no deja dar de alta ambas.
- **Pint** puede reformatear archivos que no tocaste (llaves de constructores vacíos): revisar el `git diff` antes de confirmar.
- Antes de dar por buena una pantalla, revisarla a 360 px de ancho sin scroll horizontal (grids con `grid-cols-[minmax(0,1fr)]` en móvil).

## Entorno local (Windows + Laragon)
- Sitio: `http://punto-de-venta.test` (Apache + PHP 8.3 por FastCGI). Alternativa: `php artisan serve --host=127.0.0.1 --port=8000`.
- La terminal usa PHP 8.1 por defecto: anteponer `C:\laragon\bin\php\php-8.3.8-nts-Win32-vs16-x64` al PATH para `php`, `artisan` y `composer`.
- **Para actualizar tras un `git pull`:** `php artisan migrate`, `npm run build` (`public/build` no se sube a git) y `php artisan view:clear`; `composer install` / `npm install` solo si cambió `composer.json` o `package.json`. Recordar al usuario cuando haya migraciones o algo que compilar.
- **Subida de imágenes:** `upload_tmp_dir` debe apuntar a `C:/laragon/tmp` en el `php.ini` de PHP 8.3 (con la carpeta temporal de Windows, `realpath()` falla bajo FastCGI y Livewire da «Path cannot be empty»). Tras cambiar `php.ini`, reiniciar los procesos `php-cgi`.
- Escribir archivos con Write/Edit; `Set-Content -Encoding utf8` en PowerShell 5.1 agrega BOM y rompe los `.php`.
- Usuarios de desarrollo: `admin`, `encargado`, `cajero` (contraseñas en `database/seeders/DatabaseSeeder.php`; solo para entorno local). Si `ADMIN_PASSWORD` está vacía en `.env` el seeder usa `password` solo en local.
- Túnel ngrok para probar en el teléfono: `APP_URL` con la URL https y `TRUSTED_PROXIES=*` solo en desarrollo (en producción `*` se ignora). La cámara exige HTTPS.
