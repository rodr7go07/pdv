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
- Verificación visual: Playwright viene instalado (`require('playwright')`); levantar `php artisan serve` con una base SQLite temporal (`DB_CONNECTION=sqlite DB_DATABASE=<ruta> SESSION_DRIVER=file CACHE_STORE=file`) y revisar a 1280, 390 y 800×360 px. No usar `pkill -f`/`pgrep -f` (matan el propio shell): guardar el PID en un archivo.

## Estado del proyecto
- Fases 0 a 7 terminadas (con pruebas Pest); Fase 8 solo **preparada** en `deploy/` (falta ejecutarla en un servidor real); Fase 9 sin empezar. Las decisiones técnicas van numeradas en `PLAN.md` (1–71).
- La guía de uso vive en `resources/views/guia.blade.php` (lenguaje sencillo) y `docs/GUIA_DETALLADA.md`. Todo cambio de flujo o de atajos debe actualizar ambas (hay pruebas en `GuiaTest`).
- Pendientes por confirmar con el dueño de la tienda: método de costeo, precios con impuestos incluidos y si necesita abrir el cajón de dinero. Además, el catálogo cargado casi no marca IVA: confirmar qué artículos llevan impuesto.
- Pendientes técnicos conocidos: probar con MySQL real, impresora de tickets y lector físico; ejecutar la Fase 8 en el servidor; el historial de git conserva un `punto_venta` (SQLite) subido por error con hashes de contraseñas de desarrollo (reescribir historial solo si el usuario lo pide).

## Módulos y decisiones recientes (ver `PLAN.md` para el detalle)
- **Roles:** los permisos están en el enum `Permiso` (con `grupo()` para la pantalla) y los roles viven en la base de datos (pantalla **Roles**, `roles.administrar`). El Administrador siempre tiene todos los permisos; Encargado y Cajero se editan pero no se renombran ni se borran. `RolesYPermisosSeeder` solo crea Encargado/Cajero la primera vez: **un permiso nuevo para roles existentes se da en la migración que lo crea** (ver `2026_11_02_000001_autorizacion_de_cancelaciones`). Nadie puede dar permisos que no tiene.
- **Cancelar ventas:** quien tiene `ventas.cancelar` cancela directo; un cajero solo su propia venta con el turno abierto y con un supervisor (`ventas.autorizar_cancelacion`) que escribe usuario y contraseña (`VerificarAutorizacion`). Con el turno cerrado se hace una devolución total desde la caja abierta de quien cancela (`devoluciones.es_cancelacion`); la venta sigue `completada`.
- **Inventario:** «agotado» (existencia ≤ 0) y «bajo el mínimo» (hay pero ≤ mínimo) no se traslapan (`scopeAgotados`, `scopeBajoMinimoConExistencia`).
- **Compras:** artículos con proveedor principal (`articulos.proveedor_id`) y proveedores con `dias_visita`; la sugerencia de compra es editable y se filtra por proveedor (`SugerenciaCompra`, `Proveedor::diasHastaSiguienteVisita`).
- **Importación de artículos:** `Artículos → Importar` o `php artisan articulos:importar "ruta.xlsx" --usuario=admin` (todo o nada). Acepta los encabezados de la plantilla y los de otros sistemas (`clave1…clave6`, `precio1`, `inv_min`, `(s/n) …`); columnas `existencia` (solo artículos nuevos; negativas = 0) y `proveedor` (se crea si no existe). Un granel con unidad de piezas se carga como no granel y una clave alterna ajena se omite, siempre con aviso.
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
