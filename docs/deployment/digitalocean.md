# Despliegue en DigitalOcean

Dos opciones: **Droplet** (VPS Ubuntu, control total) o **App Platform** (PaaS gestionado). Se complementan con **Managed Database**, **Spaces** (storage S3-compatible) y **Managed Redis**.

## Índice

1. [Opción A — Droplet](#opción-a--droplet)
2. [Opción B — App Platform](#opción-b--app-platform)
3. [Base de datos — Managed Database](#base-de-datos--managed-database)
4. [Storage — Spaces](#storage--spaces)
5. [Variables de entorno DigitalOcean](#variables-de-entorno-digitalocean)

---

## Opción A — Droplet

### 1. Crear Droplet

Desde la consola de DigitalOcean:
- **Imagen:** Ubuntu 22.04 LTS
- **Plan:** Basic — Shared CPU — $12/mes (2 vCPU, 2 GB RAM, recomendado mínimo)
- **Datacenter:** Seleccionar la región más cercana a tus usuarios
- **Opciones:** Habilitar Monitoring y Backups

O vía CLI con `doctl`:

```bash
# Instalar doctl
brew install doctl   # macOS
doctl auth init      # Ingresar API token

# Crear Droplet
doctl compute droplet create vamospues-api \
  --image ubuntu-22-04-x64 \
  --size s-2vcpu-2gb \
  --region nyc3 \
  --ssh-keys <fingerprint-de-tu-key>
```

### 2. Configurar el servidor

```bash
# Conectar por SSH
ssh root@<IP-DROPLET>

# Actualizar sistema
apt update && apt upgrade -y

# Instalar PHP 8.2
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
  php8.2-bcmath php8.2-redis php8.2-intl

# Instalar herramientas
apt install -y nginx supervisor git certbot python3-certbot-nginx

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Crear usuario para la app (no correr como root)
adduser --disabled-password --gecos "" vamospues
usermod -aG www-data vamospues
```

### 3. Desplegar la aplicación

```bash
# Cambiar al usuario de la app
su - vamospues

# Clonar repositorio
git clone https://github.com/tu-org/vamospues-backend.git /var/www/vamospues-api
cd /var/www/vamospues-api

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Configurar entorno
cp .env.example .env
nano .env   # Configurar con valores de producción

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
chown -R www-data:www-data /var/www/vamospues-api
chmod -R 775 /var/www/vamospues-api/storage /var/www/vamospues-api/bootstrap/cache
```

### 4. Nginx

```bash
nano /etc/nginx/sites-available/vamospues-api
```

```nginx
server {
    listen 80;
    server_name api.tudominio.com;
    root /var/www/vamospues-api/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 50M;
}
```

```bash
ln -s /etc/nginx/sites-available/vamospues-api /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# SSL
certbot --nginx -d api.tudominio.com
```

### 5. Supervisor y Cron

```bash
nano /etc/supervisor/conf.d/vamospues-worker.conf
```

```ini
[program:vamospues-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vamospues-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
stdout_logfile=/var/www/vamospues-api/storage/logs/worker.log
```

```bash
supervisorctl reread && supervisorctl update
supervisorctl start vamospues-worker:*

# Cron
crontab -u www-data -e
# Agregar: * * * * * cd /var/www/vamospues-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Opción B — App Platform

Despliegue completamente gestionado sin administrar servidores. Conecta directamente al repositorio de GitHub.

### 1. Crear app desde la consola

1. Ir a **App Platform → Create App**
2. Conectar repositorio de GitHub
3. Seleccionar branch `main`
4. Configurar:
   - **Tipo:** Web Service
   - **Runtime:** PHP 8.2
   - **Build Command:** `composer install --no-dev --optimize-autoloader`
   - **Run Command:** `heroku-php-nginx public/`

> App Platform usa buildpacks Heroku. Agregar el `Procfile`:

```
# Procfile (en la raíz del proyecto)
web: vendor/bin/heroku-php-nginx -C nginx.conf public/
worker: php artisan queue:work --sleep=3 --tries=3
```

### 2. Variables de entorno en App Platform

Configurar en **App → Settings → App-Level Environment Variables**:

```
APP_KEY         = base64:...
APP_ENV         = production
APP_DEBUG       = false
DB_CONNECTION   = mysql
DB_HOST         = ${db.HOSTNAME}        ← referencia a componente DB
DB_PORT         = ${db.PORT}
DB_DATABASE     = ${db.DATABASE}
DB_USERNAME     = ${db.USERNAME}
DB_PASSWORD     = ${db.PASSWORD}
FILESYSTEM_DISK = s3
AWS_ACCESS_KEY_ID     = ...
AWS_SECRET_ACCESS_KEY = ...
AWS_DEFAULT_REGION    = nyc3
AWS_BUCKET            = vamospues-media
AWS_ENDPOINT          = https://nyc3.digitaloceanspaces.com
```

### 3. Agregar base de datos al app

En la consola de App Platform → **Add Resource → Database → Dev Database** (MySQL).  
Las variables `${db.*}` se inyectan automáticamente.

### 4. Migraciones en App Platform

Agregar un Job de tipo "Before Deploy":

```
# En App Platform → Components → Add Job
Name: migrate
Command: php artisan migrate --force && php artisan db:seed --force
```

`db:seed` aquí solo siembra infraestructura base (settings, permisos, roles) —
NO crea ningún administrador, así que es seguro que este Job corra en cada
deploy. El primer administrador es un paso **aparte y de una sola vez** (no
lo metas en este Job, correr `app:install` en cada deploy no tiene sentido):
abre una shell del componente (**App Platform → Console**) y ejecuta

```bash
php artisan app:install \
  --first-name="Nombre" --last-name="Apellido" \
  --email="admin@midominio.com" --password="una-password-segura" \
  --site-name="Cusgo Adventures" --contact-email="info@midominio.com" \
  --currency=USD --timezone=America/El_Salvador
```

o completa el wizard web en `/install`.

---

## Base de datos — Managed Database

### Crear clúster MySQL

```bash
doctl databases create vamospues-db \
  --engine mysql \
  --version 8 \
  --region nyc3 \
  --size db-s-1vcpu-1gb \
  --num-nodes 1
```

Desde la consola: **Databases → Create → MySQL 8 → Basic plan → $15/mes**

### Configurar trusted sources

En **Database → Settings → Trusted Sources**, agregar la IP del Droplet. La base de datos NO debe ser accesible desde internet.

### Obtener credenciales

```bash
doctl databases connection vamospues-db --format Host,Port,User,Password,Database
```

```ini
DB_HOST=<cluster>.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=defaultdb
DB_USERNAME=doadmin
DB_PASSWORD=<password>
```

---

## Storage — Spaces

DigitalOcean Spaces es 100% compatible con la API de S3.

### 1. Crear Space

```bash
# Desde consola: Spaces → Create Space
# O con doctl:
doctl compute domain create  # No hay comando doctl para Spaces, usar consola
```

Desde la consola:
1. **Create Space** → Nombre: `vamospues-media`
2. **Region:** la misma del Droplet (ej. `nyc3`)
3. **CDN:** Habilitar (opcional, mejora velocidad de entrega)
4. **File Listing:** Restricted

### 2. Crear Access Keys

**API → Spaces Keys → Generate New Key**

Guarda el **Access Key** y **Secret Key**.

### 3. Configurar en Laravel

```ini
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<spaces-access-key>
AWS_SECRET_ACCESS_KEY=<spaces-secret>
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=vamospues-media
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### 4. Configurar CORS en el Space

En **Space → Settings → CORS Configurations**:

```json
[
  {
    "AllowedOrigins": ["https://tudominio.com"],
    "AllowedMethods": ["GET"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3000
  }
]
```

---

## Variables de entorno DigitalOcean

```ini
APP_NAME="VamosPues"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=<cluster>.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=defaultdb
DB_USERNAME=doadmin
DB_PASSWORD=<password>
DB_OPTIONS_SSL=true   # Managed DB requiere SSL

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=cookie
FILESYSTEM_DISK=s3

# Managed Redis (Valkey) — opcional
REDIS_HOST=<redis-cluster>.db.ondigitalocean.com
REDIS_PASSWORD=<redis-password>
REDIS_PORT=25061

# Spaces
AWS_ACCESS_KEY_ID=<spaces-key>
AWS_SECRET_ACCESS_KEY=<spaces-secret>
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=vamospues-media
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com

# Mail (usar Mailgun, SendGrid o SMTP externo)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=<mailgun-smtp-user>
MAIL_PASSWORD=<mailgun-smtp-password>
MAIL_FROM_ADDRESS=noreply@tudominio.com
```

> DigitalOcean Managed Database requiere SSL. Agregar al `config/database.php`:
> ```php
> 'options' => extension_loaded('pdo_mysql') ? [
>     PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
> ] : [],
> ```
