# Despliegue del Backend — Guía general

Documentación de despliegue del API Laravel en distintos proveedores de nube. Cada archivo cubre un proveedor específico con comandos listos para usar.

## Proveedores documentados

| Proveedor | Archivo | Complejidad | Costo aprox/mes |
|-----------|---------|-------------|-----------------|
| AWS (EC2 + RDS + S3) | [aws.md](aws.md) | Alta | $30–80 USD |
| Google Cloud Platform | [gcp.md](gcp.md) | Alta | $25–70 USD |
| DigitalOcean | [digitalocean.md](digitalocean.md) | Media | $15–40 USD |
| Railway | [railway.md](railway.md) | Baja | $5–20 USD |
| VPS genérico (Hetzner, Vultr, Linode) | [vps.md](vps.md) | Media | $5–20 USD |

---

## Requisitos del servidor

Independientemente del proveedor, el servidor debe cumplir:

### Software
| Componente | Versión mínima |
|-----------|----------------|
| PHP | 8.2+ |
| MySQL | 8.0+ (o PostgreSQL 15+) |
| Redis | 6.0+ |
| Nginx | 1.18+ (o Apache 2.4+) |
| Composer | 2.x |
| Node.js | 18+ (solo para build, no para producción) |

### Extensiones PHP requeridas
```
pdo_mysql  bcmath  ctype  curl  dom  fileinfo
gd         json    mbstring  openssl  pcre
phar       tokenizer  xml  zip
```

---

## Variables de entorno de producción

Estas son las variables críticas que deben configurarse en producción. Copiar y adaptar en el servidor:

```ini
APP_NAME="VamosPues"
APP_ENV=production
APP_KEY=                          # Generar: php artisan key:generate
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=<host-db>
DB_PORT=3306
DB_DATABASE=vamospues
DB_USERNAME=vamospues_user
DB_PASSWORD=<contraseña-segura>

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis           # Usar 'database' si no hay Redis
SESSION_DRIVER=cookie
FILESYSTEM_DISK=s3               # 's3' para cloud storage, 'local' para disco

REDIS_HOST=<host-redis>
REDIS_PASSWORD=<password-redis>
REDIS_PORT=6379

MAIL_MAILER=smtp                 # o 'ses' para AWS SES
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="VamosPues"

# Storage S3/compatible
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=vamospues-media
AWS_USE_PATH_STYLE_ENDPOINT=false  # true solo para MinIO o S3 local

# Límite de dispositivos por usuario (0 = sin límite)
LIMIT_AUTH_DEVICES=3

# Pasarelas de pago (modo producción)
PAYPAL_BASE_URI=https://api-m.paypal.com
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=

STRIPE_BASE_URI=https://api.stripe.com
STRIPE_KEY=
STRIPE_SECRET=

WOMPI_BASE_AUTH_URI=https://id.wompi.sv
WOMPI_BASE_URI=https://api.wompi.sv
WOMPI_PUBLIC_KEY=
WOMPI_PRIVATE_KEY=
```

---

## Checklist de despliegue universal

Estos pasos aplican a cualquier proveedor después de subir el código:

```bash
# 1. Instalar dependencias
composer install --no-dev --optimize-autoloader

# 2. Publicar config, vistas y archivos de Passport
php artisan vendor:publish --provider="Laravel\Passport\PassportServiceProvider"

# 3. Generar clave de la app
php artisan key:generate

# 4. Generar claves OAuth de Passport
php artisan passport:keys

# 5. Ejecutar migraciones
php artisan migrate --force

# 6. Ejecutar seeders iniciales
php artisan db:seed --force

# 7. Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Crear link simbólico para storage público
php artisan storage:link

# 9. Permisos de directorios
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Configuración de Nginx (referencia)

```nginx
server {
    listen 80;
    server_name api.tudominio.com;
    root /var/www/vamospues-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

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
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```

---

## Supervisor (Queue Workers)

El sistema usa colas para enviar notificaciones y emails. Configurar Supervisor para que el worker corra continuamente:

```ini
# /etc/supervisor/conf.d/vamospues-worker.conf
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
supervisorctl reread
supervisorctl update
supervisorctl start vamospues-worker:*
```

---

## Laravel Scheduler (Cron)

```bash
# Agregar al crontab del usuario www-data o del servidor
crontab -e

# Agregar esta línea:
* * * * * cd /var/www/vamospues-api && php artisan schedule:run >> /dev/null 2>&1
```
