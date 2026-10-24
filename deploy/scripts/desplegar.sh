#!/usr/bin/env bash
# Despliega la última versión del Punto de Venta. Ejecutar como el usuario "deploy":
#   cd /var/www/punto-de-venta && bash deploy/scripts/desplegar.sh
#
# Es seguro ejecutarlo varias veces. Si algo falla, se detiene y deja el sitio en modo mantenimiento
# con un mensaje claro (ver "Si algo sale mal" en deploy/README.md).
set -euo pipefail

cd "$(dirname "$0")/../.."
RAMA="${1:-main}"
PHP="${PHP_BIN:-php}"

if [[ ! -f .env ]]; then
  echo "Falta el archivo .env. Copia deploy/.env.production.example a .env y llénalo."; exit 1
fi

fallo() { echo; echo "✗ El despliegue falló. El sitio sigue en mantenimiento: revisa el error de arriba."; exit 1; }
trap fallo ERR

echo "==> Modo mantenimiento"
$PHP artisan down --retry=30 --refresh=15 || true

echo "==> Bajando la versión '${RAMA}'"
git fetch --all --prune
git checkout "${RAMA}"
git reset --hard "origin/${RAMA}"

echo "==> Dependencias de PHP"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Compilando archivos del sitio"
npm ci --no-audit --no-fund
npm run build

echo "==> Base de datos: migraciones y permisos"
$PHP artisan migrate --force

# Primera vez (todavía no hay usuarios): siembra roles, catálogos base, caja inicial y el administrador
# (exige ADMIN_PASSWORD en el .env). Después solo se sincronizan los permisos nuevos de cada versión.
usuarios="$($PHP artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -1 | tr -dc '0-9')"
if [[ "${usuarios:-0}" == "0" ]]; then
  echo "   Primera instalación: sembrando datos iniciales"
  $PHP artisan db:seed --force
else
  $PHP artisan db:seed --class='Database\Seeders\RolesYPermisosSeeder' --force
fi
$PHP artisan storage:link 2>/dev/null || true

echo "==> Cachés"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "==> Permisos de carpetas"
chmod -R ug+rwX storage bootstrap/cache

echo "==> Reiniciando servicios"
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart punto-de-venta-worker:* 2>/dev/null || true
$PHP artisan queue:restart

echo "==> Saliendo de mantenimiento"
$PHP artisan up
trap - ERR

echo
echo "✓ Listo. Versión desplegada: $(git rev-parse --short HEAD) — $(git log -1 --pretty=%s)"
