# Despliegue en DigitalOcean (Fase 8)

Manual para poner el Punto de Venta en un servidor de DigitalOcean. Está escrito para seguirse en orden, sin experiencia previa en servidores.

> **Estado:** los archivos de esta carpeta están **listos pero no se han ejecutado en un servidor real** (el desarrollo se hizo en Windows con Laragon). Los scripts de respaldo y restauración sí se probaron contra MySQL local. Lo demás se valida la primera vez que se instale; cada paso indica cómo comprobarlo.

## Qué se necesita

| Cosa | Para qué | Costo aproximado |
|---|---|---|
| Cuenta de DigitalOcean | El servidor | — |
| Droplet **Ubuntu 24.04 LTS**, 2 GB de RAM (1 vCPU) | Corre la aplicación, Nginx y MySQL | ~USD 12/mes |
| Un **dominio** (ej. `tienda.midominio.com`) apuntando a la IP del droplet | Para tener HTTPS | ~USD 12/año |
| **Spaces** (almacenamiento) | Guardar los respaldos fuera del servidor | ~USD 5/mes |
| Tu llave SSH | Entrar al servidor de forma segura | gratis |

**MySQL: ¿en el droplet o gestionado?** Para una sola tienda, MySQL **en el mismo droplet** es suficiente y más barato. Una base gestionada (desde ~USD 15/mes) agrega respaldos automáticos y alta disponibilidad; conviene si la tienda crece a varias sucursales. Estos scripts asumen MySQL en el droplet y hacen sus propios respaldos a Spaces.

## 1. Crear el droplet

1. En DigitalOcean: **Create → Droplets** → Ubuntu 24.04 LTS → plan de 2 GB → región cercana (por ejemplo Nueva York o San Francisco).
2. En *Authentication* elige **SSH Key** y pega tu llave pública. (No uses contraseña.)
3. Crea el droplet y anota su **IP**.
4. En tu proveedor de dominio crea un registro **A**: `tienda.midominio.com → IP-DEL-DROPLET`.

## 2. Preparar el servidor (una sola vez)

Desde tu computadora:

```bash
scp deploy/scripts/provision.sh root@IP-DEL-DROPLET:/root/
ssh root@IP-DEL-DROPLET
bash /root/provision.sh
```

El script deja instalado: PHP 8.3, Nginx, MySQL 8, Composer, Node 22, Certbot, Supervisor y el firewall (solo se permiten SSH, HTTP y HTTPS). Crea el usuario **deploy** (sin root), desactiva la contraseña y el acceso de root por SSH y pone la hora de México.

**Al final muestra la contraseña de la base de datos: anótala.** Solo se muestra una vez.

Comprobación: `ssh deploy@IP-DEL-DROPLET` debe entrar; `ssh root@IP-DEL-DROPLET` debe ser rechazado.

## 3. Instalar la aplicación

Ya como `deploy` en el servidor:

```bash
cd /var/www/punto-de-venta
git clone https://github.com/rodr7go07/pdv.git .          # repositorio privado: usa una llave de despliegue o un token
cp deploy/.env.production.example .env
nano .env                                                  # llena dominio, DB_PASSWORD y ADMIN_PASSWORD
composer install --no-dev --optimize-autoloader
php artisan key:generate                                   # guarda una copia de APP_KEY en lugar seguro
bash deploy/scripts/desplegar.sh
```

`desplegar.sh` instala dependencias, compila el sitio, crea las tablas y, en la primera instalación, carga los roles, catálogos base, la caja inicial y el usuario **admin** (con la contraseña `ADMIN_PASSWORD` del `.env`).

> Guarda la **APP_KEY**: sin ella no se pueden leer las sesiones ni datos cifrados si se reconstruye el servidor.

## 4. Nginx y HTTPS

```bash
sudo cp deploy/nginx/punto-de-venta.conf /etc/nginx/sites-available/punto-de-venta
sudo sed -i 's/TU-DOMINIO.COM/tienda.midominio.com/' /etc/nginx/sites-available/punto-de-venta
sudo ln -s /etc/nginx/sites-available/punto-de-venta /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d tienda.midominio.com --redirect --agree-tos -m tu-correo@ejemplo.com
```

Certbot renueva el certificado solo (hay un temporizador del sistema). Comprobación: abre `https://tienda.midominio.com/up` — debe responder sin error — y luego `https://tienda.midominio.com` para iniciar sesión con **admin**.

## 5. Colas y tareas programadas

```bash
sudo cp deploy/supervisor/punto-de-venta-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/cron/punto-de-venta /etc/cron.d/punto-de-venta
sudo chmod 644 /etc/cron.d/punto-de-venta
```

Comprobación: `sudo supervisorctl status` debe mostrar `punto-de-venta-worker` en `RUNNING`.

## 6. Respaldos diarios a Spaces

1. En DigitalOcean crea un **Space** (por ejemplo `mi-tienda-respaldos`, mismo región) y una **llave de acceso** (Spaces Keys).
2. Crea el archivo de configuración (solo lo puede leer el sistema):

   ```bash
   sudo nano /etc/pdv-respaldo.env      # copia aquí las variables SPACES_* y URL_SITIO del .env.production.example
   sudo chmod 600 /etc/pdv-respaldo.env && sudo chown deploy /etc/pdv-respaldo.env
   ```
3. Prueba a mano: `bash deploy/scripts/respaldo.sh` — debe decir «Subido a s3://…».
4. El cron (`/etc/cron.d/punto-de-venta`) lo repite todos los días a las **2:30 a.m.**, conserva **30 días** en Spaces y 7 días en el servidor.

### Probar la restauración (hazlo al instalar y cada mes)

Un respaldo que nunca se probó no es un respaldo.

```bash
bash deploy/scripts/restaurar.sh --probar
```

Restaura el último respaldo en una base temporal, compara las cifras con la real y la borra. Debe terminar en **«La restauración funciona»**.

### Restaurar de verdad (si se perdió o dañó la base)

```bash
aws --endpoint-url https://nyc3.digitaloceanspaces.com s3 cp s3://mi-tienda-respaldos/respaldos-pdv/ARCHIVO.sql.gz .   # si el respaldo está en Spaces
bash deploy/scripts/restaurar.sh --real ARCHIVO.sql.gz
```

Pide escribir `SI`, deja el sitio en mantenimiento, hace un respaldo de seguridad de lo actual y restaura.

## 7. Monitoreo básico

* `deploy/scripts/salud.sh` corre cada 5 minutos y revisa: que `/up` responda, el espacio en disco (aviso al 85%), que Nginx/PHP/MySQL estén activos, que el último respaldo tenga menos de 26 horas y que no haya errores nuevos en el registro de Laravel. Si algo falla escribe en `/var/log/punto-de-venta-salud.log` y avisa por correo (`AVISO_CORREO`) o por webhook (`AVISO_WEBHOOK`, por ejemplo un canal de Slack o Discord).
* Además activa en DigitalOcean **Monitoring → Alerts** (CPU, memoria, disco) y un **Uptime check** a `https://tienda.midominio.com/up` con aviso a tu correo.
* Los errores quedan en `storage/logs/laravel-AAAA-MM-DD.log` (se conservan 30 días).

## 8. Actualizar a una versión nueva

```bash
ssh deploy@IP-DEL-DROPLET
cd /var/www/punto-de-venta && bash deploy/scripts/desplegar.sh
```

Pone el sitio en mantenimiento unos segundos, baja la última versión, actualiza la base y los permisos, reconstruye cachés y lo vuelve a abrir. Haz un respaldo antes de actualizar (`bash deploy/scripts/respaldo.sh`).

## Si algo sale mal

| Síntoma | Qué hacer |
|---|---|
| El despliegue falló y el sitio dice «mantenimiento» | Lee el error que mostró el script, corrígelo y vuelve a ejecutar `desplegar.sh`. Si necesitas abrir el sitio ya: `php artisan up`. |
| Página en blanco o error 500 | `tail -n 50 storage/logs/laravel-$(date +%F).log` y `sudo tail -n 50 /var/log/nginx/punto-de-venta.error.log` |
| Error de permisos en `storage` | `sudo chown -R deploy:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache` |
| «No se puede conectar a la base de datos» | Revisa `DB_PASSWORD` en `.env`, que MySQL esté activo (`systemctl status mysql`) y vuelve a `php artisan config:cache`. |
| Las imágenes y logotipos no cargan | `php artisan storage:link` |
| Se perdió la base de datos | Sección 6, «Restaurar de verdad». |
| Se perdió el servidor completo | Crea un droplet nuevo, repite las secciones 2 a 5 con la misma `APP_KEY` y restaura el último respaldo. |

## Seguridad: lo que ya está hecho y lo que depende de ti

Ya está: sin acceso por contraseña ni como root, firewall, fail2ban, HTTPS obligatorio, cookies seguras, `APP_DEBUG=false`, archivos ocultos bloqueados en Nginx.
Depende de ti: elegir contraseñas largas, no compartir la llave SSH ni la `APP_KEY`, y mantener el servidor actualizado (`sudo apt update && sudo apt upgrade` una vez al mes; Ubuntu también aplica parches de seguridad automáticamente).
