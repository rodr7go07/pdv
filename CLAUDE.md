# Instrucciones para Claude Code — Punto de Venta

## Idioma
- Responder **siempre en español**: explicaciones, resúmenes, mensajes de commit y comentarios en código.
- Nombres de tablas, modelos y columnas en español (ej. `articulos`, `ventas`, `venta_partidas`), salvo convenciones de Laravel (`id`, `created_at`, etc.).

## Git
- **No agregar** `Co-Authored-By` ni ninguna línea que atribuya coautoría a Claude en los commits.
- **No agregar** "Generated with Claude Code" ni firmas similares en commits ni en PRs.
- Commits pequeños por funcionalidad, con mensaje descriptivo en español.

## Plan de trabajo
- El plan completo está en `PLAN.md`, dividido en fases.
- Trabajar **una fase a la vez** y en orden, salvo que se indique otra cosa.
- Al terminar cada tarea, marcarla como completada (`[x]`) en `PLAN.md`.
- Respetar siempre la sección "Decisiones técnicas clave" de `PLAN.md`. Si algo obliga a romper una de esas decisiones, detenerse y preguntar antes.
- Antes de iniciar una fase, presentar un breve plan de implementación (migraciones, modelos, componentes, pruebas) y esperar confirmación.
- Revisar la sección "Pendientes por decidir" de `PLAN.md` al inicio de cada fase. Si un pendiente bloquea la fase actual, recordarlo y pedir la decisión antes de implementar. Al resolverse, anotar la decisión en "Decisiones técnicas clave" y marcar el pendiente.

## Entorno
- Desarrollo local en **Windows con Laragon** (MySQL). Los comandos de terminal deben ser compatibles con PowerShell.
- Producción: Droplet de DigitalOcean con Nginx.

## Convenciones de código
- Lógica de negocio en `app/Actions` (o `app/Services`); los componentes Livewire solo orquestan la UI.
- Operaciones que tocan inventario, ventas o caja siempre dentro de `DB::transaction()` con `lockForUpdate()` donde aplique.
- Importes en `DECIMAL`, nunca `float`. Cálculos monetarios con `brick/math` o bcmath.
- Validaciones con Form Requests o reglas de Livewire; mensajes de validación en español.
- Autorización con permisos de `spatie/laravel-permission`, verificados tanto en UI como en backend.
- Toda pantalla debe ser usable en teléfono (360px de ancho) y en PC.
- Entregar archivos completos al hacer cambios importantes, no fragmentos sueltos.
- Soluciones definitivas y limpias; evitar parches temporales.

## Pruebas
- Pest para pruebas de funcionalidad de cada Action crítica (venta, cancelación, compra, ajuste, corte).
- Ejecutar las pruebas antes de dar una tarea por terminada.

## Estado del proyecto
- Fases 0 a 7 terminadas (con pruebas Pest); Fase 8 solo **preparada** en `deploy/` (falta ejecutarla en un servidor real); Fase 9 sin empezar. Las decisiones técnicas van numeradas en `PLAN.md` (1–57).
- La guía de uso vive en `resources/views/guia.blade.php` (lenguaje sencillo) y `docs/GUIA_DETALLADA.md`. Todo cambio de flujo o de atajos debe actualizar ambas.
- Pendientes por confirmar con el dueño de la tienda: método de costeo, precios con impuestos incluidos y si necesita abrir el cajón de dinero.

## Reglas aprendidas (no repetir errores)
- **Dinero e inventario:** la existencia solo cambia con `RegistrarMovimientoInventario` y el saldo del cliente solo con `RegistrarMovimientoCliente`. Bloquear los artículos siempre **en orden de id** (venta, devolución, cancelaciones, conteo) y generar folios con `Services\Folios` dentro de la transacción.
- **Sin cliente solo existe el precio 1**; las listas 2 y 3 son del cliente.
- **Corte ciego:** quien no es supervisor no ve el efectivo esperado del corte X ni de su turno abierto.
- **Livewire:** los middleware de usuario activo y permisos están registrados como persistentes (`AppServiceProvider`). No poner contenido dinámico en `<x-slot name="acciones">` (el encabezado no se vuelve a pintar); ponerlo dentro del componente.
- **Fechas de filtros:** usar `App\Support\Fecha` (ignora fechas inválidas) y rangos `>= inicio` / `<= fin`, nunca `whereDate` en tablas grandes.
- **Pruebas:** corren solo con SQLite; `tests/TestCase.php` se niega a correr si la configuración está en caché o la conexión no es SQLite. No usar `config:cache` en local.
- **Alpine en páginas sin componente Livewire:** el layout carga `@livewireStyles` y `@livewireScripts` para que Alpine exista también en `/guia`. En `x-show` evitar llamar métodos del `x-data`; usar getters o un `x-effect`.
- Antes de dar por buena una pantalla, revisarla a 360 px de ancho sin scroll horizontal (grids con `grid-cols-[minmax(0,1fr)]` en móvil).

## Entorno local (Windows + Laragon)
- Sitio: `http://punto-de-venta.test` (Apache + PHP 8.3 por FastCGI). Alternativa: `php artisan serve --host=127.0.0.1 --port=8000`.
- La terminal usa PHP 8.1 por defecto: anteponer `C:\laragon\bin\php\php-8.3.8-nts-Win32-vs16-x64` al PATH para `php`, `artisan` y `composer`.
- **Subida de imágenes:** `upload_tmp_dir` debe apuntar a `C:/laragon/tmp` en el `php.ini` de PHP 8.3 (con la carpeta temporal de Windows, `realpath()` falla bajo FastCGI y Livewire da «Path cannot be empty»). Tras cambiar `php.ini`, reiniciar los procesos `php-cgi`.
- Escribir archivos con Write/Edit; `Set-Content -Encoding utf8` en PowerShell 5.1 agrega BOM y rompe los `.php`.
- Usuarios de desarrollo: `admin`, `encargado`, `cajero` (contraseñas en `database/seeders/DatabaseSeeder.php`; solo para entorno local).
