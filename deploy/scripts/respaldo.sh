#!/usr/bin/env bash
# Respaldo diario de la base de datos.
#  - Hace un volcado comprimido con fecha y hora.
#  - Lo sube a DigitalOcean Spaces (si hay llaves configuradas).
#  - Borra respaldos viejos, local y remotamente (retención en DIAS_RETENCION).
#
# Configuración: variables del .env de la app o de /etc/pdv-respaldo.env (ver deploy/.env.production.example).
# Prueba local sin subir nada:  SOLO_LOCAL=1 bash deploy/scripts/respaldo.sh
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
[[ -f /etc/pdv-respaldo.env ]] && set -a && source /etc/pdv-respaldo.env && set +a

leer_env() { grep -E "^$1=" "${RAIZ}/.env" 2>/dev/null | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

DB_HOST="${DB_HOST:-$(leer_env DB_HOST)}";           DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-$(leer_env DB_PORT)}";           DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-$(leer_env DB_DATABASE)}"
DB_USERNAME="${DB_USERNAME:-$(leer_env DB_USERNAME)}"
DB_PASSWORD="${DB_PASSWORD:-$(leer_env DB_PASSWORD)}"
MYSQLDUMP="${MYSQLDUMP_BIN:-mysqldump}"

DIR_RESPALDOS="${DIR_RESPALDOS:-${RAIZ}/storage/respaldos}"
DIAS_RETENCION="${DIAS_RETENCION:-30}"
SPACES_BUCKET="${SPACES_BUCKET:-}"          # ej. mi-tienda-respaldos
SPACES_ENDPOINT="${SPACES_ENDPOINT:-}"      # ej. https://nyc3.digitaloceanspaces.com
SPACES_PREFIJO="${SPACES_PREFIJO:-respaldos-pdv}"

[[ -n "${DB_DATABASE}" && -n "${DB_USERNAME}" ]] || { echo "Faltan datos de la base de datos (DB_DATABASE / DB_USERNAME)."; exit 1; }

mkdir -p "${DIR_RESPALDOS}"
SELLO="$(date +%Y-%m-%d_%H%M%S)"
ARCHIVO="${DIR_RESPALDOS}/${DB_DATABASE}_${SELLO}.sql.gz"

echo "[$(date '+%F %T')] Respaldando ${DB_DATABASE}…"
MYSQL_PWD="${DB_PASSWORD}" "${MYSQLDUMP}" --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" \
  --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 \
  "${DB_DATABASE}" | gzip -9 > "${ARCHIVO}"

# Un respaldo vacío es peor que ninguno: se revisa antes de seguir.
if [[ ! -s "${ARCHIVO}" ]] || ! gzip -t "${ARCHIVO}"; then
  echo "✗ El respaldo salió dañado o vacío."; rm -f "${ARCHIVO}"; exit 1
fi
echo "   Archivo: ${ARCHIVO} ($(du -h "${ARCHIVO}" | cut -f1))"

if [[ "${SOLO_LOCAL:-0}" != "1" && -n "${SPACES_BUCKET}" && -n "${SPACES_ENDPOINT}" ]]; then
  export AWS_ACCESS_KEY_ID="${SPACES_KEY:?Falta SPACES_KEY}"
  export AWS_SECRET_ACCESS_KEY="${SPACES_SECRET:?Falta SPACES_SECRET}"
  export AWS_DEFAULT_REGION="${SPACES_REGION:-us-east-1}"
  destino="s3://${SPACES_BUCKET}/${SPACES_PREFIJO}/$(basename "${ARCHIVO}")"

  aws --endpoint-url "${SPACES_ENDPOINT}" s3 cp "${ARCHIVO}" "${destino}" --only-show-errors
  echo "   Subido a ${destino}"

  # Retención remota: se borra lo anterior a DIAS_RETENCION días (la fecha va en el nombre del archivo).
  limite="$(date -d "-${DIAS_RETENCION} days" +%Y-%m-%d)"
  aws --endpoint-url "${SPACES_ENDPOINT}" s3 ls "s3://${SPACES_BUCKET}/${SPACES_PREFIJO}/" | awk '{print $4}' | while read -r nombre; do
    fecha="$(echo "${nombre}" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' | head -1 || true)"
    if [[ -n "${fecha}" && "${fecha}" < "${limite}" ]]; then
      aws --endpoint-url "${SPACES_ENDPOINT}" s3 rm "s3://${SPACES_BUCKET}/${SPACES_PREFIJO}/${nombre}" --only-show-errors
      echo "   Borrado respaldo viejo: ${nombre}"
    fi
  done
else
  echo "   (Sin Spaces configurado o SOLO_LOCAL=1: el respaldo queda solo en este equipo.)"
fi

# Retención local: se conservan 7 días aunque haya copia remota.
find "${DIR_RESPALDOS}" -name '*.sql.gz' -mtime +"$([[ -n "${SPACES_BUCKET}" ]] && echo 7 || echo "${DIAS_RETENCION}")" -delete

# Marca de último respaldo exitoso (la revisa salud.sh)
date +%s > "${DIR_RESPALDOS}/.ultimo-exito"
echo "[$(date '+%F %T')] ✓ Respaldo terminado."
