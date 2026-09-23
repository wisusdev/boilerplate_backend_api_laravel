# Despliegue en VPS Genérico

Guía para cualquier proveedor de VPS con Ubuntu 22.04: **Hetzner**, **Vultr**, **Linode/Akamai**, **Contabo**, **OVHcloud** u otros. Es la opción más económica ($4–20/mes).

## Índice

1. [Requisitos del VPS](#requisitos-del-vps)
2. [Proveedores recomendados](#proveedores-recomendados)
3. [Configuración inicial del servidor](#configuración-inicial-del-servidor)
4. [Instalar el stack PHP](#instalar-el-stack-php)
5. [Configurar MySQL local](#configurar-mysql-local)
6. [Configurar Redis local](#configurar-redis-local)
7. [Desplegar la aplicación](#desplegar-la-aplicación)
8. [Configurar Nginx y SSL](#configurar-nginx-y-ssl)
9. [Configurar Supervisor y Cron](#configurar-supervisor-y-cron)
10. [Actualizaciones y deploy](#actualizaciones-y-deploy)

---

## Requisitos del VPS

| Carga | RAM | CPU | Almacenamiento |
|-------|-----|-----|----------------|
| Desarrollo / staging | 1 GB | 1 vCPU | 20 GB SSD |
| Producción pequeña (<100 usuarios/día) | 2 GB | 1–2 vCPU | 40 GB SSD |
| Producción media (100–1000 usuarios/día) | 4 GB | 2 vCPU | 80 GB SSD |

---

## Proveedores recomendados

| Proveedor | Plan mínimo recomendado | Precio/mes | Datacenter latam |
|-----------|------------------------|------------|------------------|
| **Hetzner** | CX22 (2 vCPU, 4 GB) | ~$4.5 USD | No (EU/US) |
| **Vultr** | Regular Cloud 2 GB | $12 USD | Miami, São Paulo |
| **Linode/Akamai** | Nanode 1 GB → Linode 2 GB | $5–10 USD | Miami |
| **Contabo** | VPS S | $5 USD | EU/US |
| **OVHcloud** | VPS Starter | $6 USD | Miami |

---

## Configuración inicial del servidor

```bash
# Conectar al servidor (como root)
ssh root@<IP-VPS>

# Actualizar sistema
apt update && apt upgrade -y

# Crear usuario no-root para mayor seguridad
adduser deploy
usermod -aG sudo deploy

# Copiar tu llave SSH al usuario deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Deshabilitar login por contraseña (seguridad)
sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/#PasswordAuthentication no/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd

# Configurar firewall básico
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Verificar que sigue accesible
ssh deploy@<IP-VPS>
```

---

## Instalar el stack PHP

```bash
# Como usuario deploy (con sudo)
ssh deploy@<IP-VPS>

# PHP 8.2 y extensiones
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-pdo \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
  php8.2-gd php8.2-bcmath php8.2-redis php8.2-intl \
  php8.2-tokenizer php8.2-dom

# Ajustar php.ini para producción
sudo nano /etc/php/8.2/fpm/php.ini
```

Cambios recomendados en `php.ini`:
```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
max_execution_time = 300
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
```

```bash
# Nginx
sudo apt install -y nginx

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version

# Supervisor
sudo apt install -y supervisor

# Certbot
sudo apt install -y certbot python3-certbot-nginx

# Git
sudo apt install -y git
```

---

## Configurar MySQL local

```bash
sudo apt install -y mysql-server

# Asegurar instalación
sudo mysql_secure_installation
# Responder: Y, Y (validar contraseñas), ingresar contraseña root, Y, Y, Y, Y

# Crear base de datos y usuario para la app
sudo mysql -u root -p
```

```sql
CREATE DATABASE vamospues CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vamospues_user'@'localhost' IDENTIFIED BY '<contraseña-segura>';
GRANT ALL PRIVILEGES ON vamospues.* TO 'vamospues_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Verificar conexión
mysql -u vamospues_user -p vamospues
```

---

## Configurar Redis local

```bash
sudo apt install -y redis-server

# Configurar Redis
sudo nano /etc/redis/redis.conf
```

Cambios en `redis.conf`:
```ini
# Cambiar a socket UNIX para mejor rendimiento (opcional)
unixsocket /var/run/redis/redis-server.sock
unixsocketperm 770

# Agregar contraseña (recomendado)
requirepass <contraseña-redis>

# Límite de memoria
maxmemory 128mb
maxmemory-policy allkeys-lru
```

```bash
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Agregar www-data al grupo redis
sudo usermod -aG redis www-data

# Verificar
redis-cli ping   # Debe responder PONG
```

---

## Desplegar la aplicación

```bash
# Crear directorio
sudo mkdir -p /var/www/vamospues-api
sudo chown deploy:deploy /var/www/vamospues-api

# Clonar repositorio
git clone https://github.com/tu-org/vamospues-backend.git /var/www/vamospues-api
cd /var/www/vamospues-api

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Configurar entorno
cp .env.example .env
nano .env
```

Variables mínimas a completar en `.env`:
```ini
APP_ENV=production
APP_KEY=                    # Se genera en el siguiente paso
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

DB_HOST=127.0.0.1
DB_DATABASE=vamospues
DB_USERNAME=vamospues_user
DB_PASSWORD=<contraseña-db>

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<contraseña-redis>
REDIS_PORT=6379

FILESYSTEM_DISK=local       # O 's3' si usas un bucket externo
```

```bash
# Generar claves
php artisan key:generate
php artisan passport:keys

# Base de datos
php artisan migrate --force

# Solo infraestructura base (settings, permisos, roles) — NO crea ningún
# administrador. Ver docs/deployment/production-checklist.md §1.3.
php artisan db:seed --force

# Primer administrador (el seed no crea uno): instalador estilo WordPress.
php artisan app:install \
  --first-name="Nombre" --last-name="Apellido" \
  --email="admin@midominio.com" --password="una-password-segura" \
  --site-name="Cusgo Adventures" --contact-email="info@midominio.com" \
  --currency=USD --timezone=America/El_Salvador

# Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link

# Permisos
sudo chown -R www-data:www-data /var/www/vamospues-api
sudo chmod -R 775 /var/www/vamospues-api/storage
sudo chmod -R 775 /var/www/vamospues-api/bootstrap/cache
# Dar acceso al usuario deploy para git pull
sudo chown deploy:www-data /var/www/vamospues-api
sudo chmod g+s /var/www/vamospues-api
```

---

## Configurar Nginx y SSL

```bash
sudo nano /etc/nginx/sites-available/vamospues-api
```

```nginx
server {
    listen 80;
    server_name api.tudominio.com;
    root /var/www/vamospues-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/vamospues-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Obtener SSL
sudo certbot --nginx -d api.tudominio.com
# Certbot modifica el archivo de Nginx automáticamente para HTTPS
```

---

## Configurar Supervisor y Cron

```bash
sudo nano /etc/supervisor/conf.d/vamospues-worker.conf
```

```ini
[program:vamospues-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vamospues-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/vamospues-api/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vamospues-worker:*
sudo supervisorctl status

# Cron para el scheduler de Laravel
sudo crontab -u www-data -e
# Agregar:
* * * * * cd /var/www/vamospues-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Actualizaciones y deploy

Script reutilizable para desplegar nuevas versiones:

```bash
# /home/deploy/deploy.sh
#!/bin/bash
set -e

APP_DIR="/var/www/vamospues-api"

echo "==> Pull de cambios..."
cd $APP_DIR
git pull origin main

echo "==> Instalando dependencias..."
composer install --no-dev --optimize-autoloader

echo "==> Ejecutando migraciones..."
php artisan migrate --force

echo "==> Limpiando caché..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "==> Optimizando..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Reiniciando queue workers..."
sudo supervisorctl restart vamospues-worker:*

echo "==> Deploy completado: $(date)"
```

```bash
chmod +x /home/deploy/deploy.sh

# Permitir al usuario deploy reiniciar supervisor sin password
echo "deploy ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart vamospues-worker:*" | sudo tee /etc/sudoers.d/deploy-supervisor

# Ejecutar deploy
./deploy.sh
```

---

## Monitoreo básico

```bash
# Ver logs de la app
tail -f /var/www/vamospues-api/storage/logs/laravel.log

# Ver logs de Nginx
tail -f /var/log/nginx/error.log

# Ver estado de los workers
sudo supervisorctl status

# Ver uso de recursos
htop
df -h
free -h
```
