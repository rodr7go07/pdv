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
