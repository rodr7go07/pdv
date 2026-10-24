#!/usr/bin/env bash
# Revisión de salud del sistema (cada 5 minutos desde cron). Escribe en el registro y, si hay
# problemas, manda un aviso (correo con `mail`, o un webhook si defines AVISO_WEBHOOK, ej. Slack/Discord).
#   Variables opcionales en /etc/pdv-respaldo.env: URL_SITIO, AVISO_CORREO, AVISO_WEBHOOK
set -uo pipefail

RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
[[ -f /etc/pdv-respaldo.env ]] && set -a && source /etc/pdv-respaldo.env && set +a

URL_SITIO="${URL_SITIO:-http://127.0.0.1}"
DIR_RESPALDOS="${DIR_RESPALDOS:-${RAIZ}/storage/respaldos}"
problemas=()

# 1. El sitio responde (Laravel expone /up)
codigo="$(curl -s -o /dev/null -m 10 -w '%{http_code}' "${URL_SITIO}/up" || echo 000)"
[[ "${codigo}" == "200" ]] || problemas+=("El sitio no responde bien (/up devolvió ${codigo}).")

# 2. Espacio en disco
uso="$(df --output=pcent "${RAIZ}" | tail -1 | tr -dc '0-9')"
[[ "${uso}" -lt 85 ]] || problemas+=("El disco está al ${uso}%.")

# 3. Servicios
for servicio in nginx php8.3-fpm mysql; do
  systemctl is-active --quiet "${servicio}" || problemas+=("El servicio ${servicio} está detenido.")
done

# 4. Último respaldo exitoso: no debe tener más de 26 horas
if [[ -f "${DIR_RESPALDOS}/.ultimo-exito" ]]; then
  edad=$(( $(date +%s) - $(cat "${DIR_RESPALDOS}/.ultimo-exito") ))
  [[ ${edad} -lt 93600 ]] || problemas+=("El último respaldo exitoso fue hace $(( edad / 3600 )) horas.")
else
  problemas+=("Todavía no hay ningún respaldo exitoso.")
fi

# 5. Errores nuevos en el registro de Laravel de hoy
log="${RAIZ}/storage/logs/laravel-$(date +%F).log"
if [[ -f "${log}" ]]; then
  nuevos="$(grep -c '\.ERROR:' "${log}" || true)"
  previo="$(cat "${RAIZ}/storage/.errores-vistos" 2>/dev/null || echo 0)"
  if [[ "${nuevos}" -gt "${previo}" ]]; then
    problemas+=("Hay $(( nuevos - previo )) error(es) nuevo(s) en ${log}.")
  fi
  echo "${nuevos}" > "${RAIZ}/storage/.errores-vistos"
fi

if [[ ${#problemas[@]} -eq 0 ]]; then
  echo "[$(date '+%F %T')] OK"
  exit 0
fi

mensaje="Punto de Venta — $(hostname): $(printf '%s ' "${problemas[@]}")"
echo "[$(date '+%F %T')] PROBLEMA: ${mensaje}"
[[ -n "${AVISO_CORREO:-}" ]] && command -v mail >/dev/null && echo "${mensaje}" | mail -s "Problema en el Punto de Venta" "${AVISO_CORREO}"
[[ -n "${AVISO_WEBHOOK:-}" ]] && curl -s -m 10 -X POST -H 'Content-Type: application/json' -d "{\"text\":\"${mensaje}\"}" "${AVISO_WEBHOOK}" >/dev/null
exit 1
