#!/usr/bin/env bash
# Prepara un droplet nuevo de Ubuntu 24.04 LTS para el Punto de Venta.
# Ejecutar COMO ROOT, UNA SOLA VEZ, justo después de crear el droplet:
#   bash provision.sh
#
# Qué hace: usuario no root "deploy", llave SSH, firewall (UFW), PHP 8.3-FPM, Nginx, MySQL 8,
# Composer, Node 22, Certbot, Supervisor, zona horaria de México y arranque automático de servicios.
set -euo pipefail

if [[ $EUID -ne 0 ]]; then echo "Ejecuta este script como root."; exit 1; fi

USUARIO="deploy"
APP_DIR="/var/www/punto-de-venta"
DB_NOMBRE="punto_venta"
DB_USUARIO="pdv"

echo "==> Zona horaria"
timedatectl set-timezone America/Mexico_City

echo "==> Actualizando el sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y
apt-get install -y software-properties-common curl git unzip ufw fail2ban supervisor nginx certbot python3-certbot-nginx awscli

echo "==> PHP 8.3"
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-sqlite3
sed -i 's/^;*upload_max_filesize.*/upload_max_filesize = 10M/; s/^;*post_max_size.*/post_max_size = 12M/' /etc/php/8.3/fpm/php.ini
systemctl enable --now php8.3-fpm

echo "==> Composer y Node 22"
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

echo "==> MySQL 8"
apt-get install -y mysql-server
systemctl enable --now mysql
DB_CLAVE="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NOMBRE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_CLAVE}';"
mysql -e "ALTER USER '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_CLAVE}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NOMBRE}.* TO '${DB_USUARIO}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Usuario ${USUARIO} (sin root) con la llave SSH del root"
if ! id "${USUARIO}" &>/dev/null; then
  adduser --disabled-password --gecos "" "${USUARIO}"
  usermod -aG sudo,www-data "${USUARIO}"
  echo "${USUARIO} ALL=(ALL) NOPASSWD:/usr/bin/systemctl reload php8.3-fpm,/usr/bin/systemctl reload nginx,/usr/bin/supervisorctl" > /etc/sudoers.d/${USUARIO}
  chmod 440 /etc/sudoers.d/${USUARIO}
  mkdir -p /home/${USUARIO}/.ssh
  cp /root/.ssh/authorized_keys /home/${USUARIO}/.ssh/authorized_keys
  chown -R ${USUARIO}:${USUARIO} /home/${USUARIO}/.ssh
  chmod 700 /home/${USUARIO}/.ssh && chmod 600 /home/${USUARIO}/.ssh/authorized_keys
fi

echo "==> SSH solo con llave y sin entrar como root"
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/; s/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl reload ssh

echo "==> Firewall (UFW): solo SSH, HTTP y HTTPS"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now fail2ban

echo "==> Carpeta de la aplicación"
mkdir -p "${APP_DIR}"
chown -R ${USUARIO}:www-data "${APP_DIR}"

cat <<FIN

LISTO. Anota estos datos (se muestran una sola vez):

  Base de datos : ${DB_NOMBRE}
  Usuario MySQL : ${DB_USUARIO}
  Contraseña    : ${DB_CLAVE}

Siguientes pasos (ver deploy/README.md, sección 3 en adelante):
  1. Entra como ${USUARIO}:  ssh ${USUARIO}@IP-DEL-DROPLET
  2. Clona el repositorio en ${APP_DIR}, crea el .env y ejecuta deploy/scripts/desplegar.sh
FIN
