#!/usr/bin/env bash
# Restaura un respaldo en una base de datos.
#
#  PRUEBA (no toca la base real): crea/vacía "<base>_restauracion" y compara cifras con la base real.
#    bash deploy/scripts/restaurar.sh --probar [archivo.sql.gz]
#
#  RESTAURAR DE VERDAD (pisa la base real; se pide escribir SI):
#    bash deploy/scripts/restaurar.sh --real archivo.sql.gz
#
# Sin archivo, usa el respaldo más reciente de la carpeta de respaldos.
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
[[ -f /etc/pdv-respaldo.env ]] && set -a && source /etc/pdv-respaldo.env && set +a
leer_env() { grep -E "^$1=" "${RAIZ}/.env" 2>/dev/null | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

DB_HOST="${DB_HOST:-$(leer_env DB_HOST)}";           DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-$(leer_env DB_PORT)}";           DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-$(leer_env DB_DATABASE)}"
DB_USERNAME="${DB_USERNAME:-$(leer_env DB_USERNAME)}"
DB_PASSWORD="${DB_PASSWORD:-$(leer_env DB_PASSWORD)}"
MYSQL="${MYSQL_BIN:-mysql}"
DIR_RESPALDOS="${DIR_RESPALDOS:-${RAIZ}/storage/respaldos}"

modo="${1:-}"; archivo="${2:-}"
[[ "${modo}" == "--probar" || "${modo}" == "--real" ]] || { sed -n '2,12p' "$0"; exit 1; }

if [[ -z "${archivo}" ]]; then
  archivo="$(ls -1t "${DIR_RESPALDOS}"/*.sql.gz 2>/dev/null | head -1 || true)"
fi
[[ -n "${archivo}" && -f "${archivo}" ]] || { echo "No encuentro el archivo de respaldo."; exit 1; }
gzip -t "${archivo}" || { echo "El archivo de respaldo está dañado."; exit 1; }

sql() { MYSQL_PWD="${DB_PASSWORD}" "${MYSQL}" --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" --default-character-set=utf8mb4 "$@"; }

if [[ "${modo}" == "--probar" ]]; then
  destino="${DB_DATABASE}_restauracion"
  echo "==> Restaurando ${archivo} en ${destino} (la base real no se toca)"
  sql -e "DROP DATABASE IF EXISTS \`${destino}\`; CREATE DATABASE \`${destino}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  gunzip -c "${archivo}" | sql "${destino}"

  echo "==> Comparando cifras (base real vs. restaurada)"
  ok=1
  for tabla in users articulos ventas venta_partidas clientes movimientos_inventario compras turnos; do
    real="$(sql -N -B "${DB_DATABASE}" -e "SELECT COUNT(*) FROM \`${tabla}\`" 2>/dev/null || echo '?')"
    rest="$(sql -N -B "${destino}" -e "SELECT COUNT(*) FROM \`${tabla}\`" 2>/dev/null || echo '?')"
    marca="✓"; [[ "${real}" == "${rest}" ]] || { marca="≠"; }
    printf '   %-24s real=%-8s restaurada=%-8s %s\n' "${tabla}" "${real}" "${rest}" "${marca}"
    [[ "${marca}" == "✓" ]] || ok=0
  done
  sql -e "DROP DATABASE \`${destino}\`;"
  if [[ ${ok} -eq 1 ]]; then echo "✓ La restauración funciona: las cifras coinciden (a la hora del respaldo)."; else
    echo "⚠ Hay diferencias. Es normal si hubo ventas después del respaldo; revisa que las tablas no estén vacías."; fi
  exit 0
fi

echo "ATENCIÓN: esto REEMPLAZA toda la base '${DB_DATABASE}' con el respaldo:"
echo "  ${archivo}"
read -r -p "Escribe SI en mayúsculas para continuar: " confirmacion
[[ "${confirmacion}" == "SI" ]] || { echo "Cancelado."; exit 1; }

echo "==> Poniendo el sitio en mantenimiento y haciendo un respaldo de seguridad de lo actual"
( cd "${RAIZ}" && php artisan down ) || true
bash "$(dirname "$0")/respaldo.sh" || true
sql -e "DROP DATABASE IF EXISTS \`${DB_DATABASE}\`; CREATE DATABASE \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "${archivo}" | sql "${DB_DATABASE}"
( cd "${RAIZ}" && php artisan up )
echo "✓ Base restaurada desde ${archivo}"
