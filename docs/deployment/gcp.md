# Despliegue en Google Cloud Platform (GCP)

Dos opciones principales: **Compute Engine** (VM clásica, similar a EC2) o **Cloud Run** (contenedores serverless, más moderno). Se complementan con **Cloud SQL**, **Cloud Storage** y **Cloud Memorystore**.

## Índice

1. [Opción A — Compute Engine](#opción-a--compute-engine)
2. [Opción B — Cloud Run con Docker](#opción-b--cloud-run-con-docker)
3. [Base de datos — Cloud SQL](#base-de-datos--cloud-sql)
4. [Storage — Cloud Storage](#storage--cloud-storage)
5. [Variables de entorno GCP](#variables-de-entorno-gcp)
6. [Dominio y SSL](#dominio-y-ssl)

---

## Prerrequisitos

```bash
# Instalar Google Cloud CLI
curl https://sdk.cloud.google.com | bash
exec -l $SHELL
gcloud init

# Configurar proyecto
gcloud config set project vamospues-prod
gcloud config set compute/region us-central1
```

---

## Opción A — Compute Engine

Ideal si prefieres control total del servidor (igual que AWS EC2).

### 1. Crear instancia

```bash
gcloud compute instances create vamospues-api \
  --machine-type=e2-small \
  --image-family=ubuntu-2204-lts \
  --image-project=ubuntu-os-cloud \
  --boot-disk-size=20GB \
  --tags=http-server,https-server \
  --zone=us-central1-a
```

### 2. Reglas de firewall

```bash
# Permitir HTTP y HTTPS
gcloud compute firewall-rules create allow-http \
  --allow tcp:80 --target-tags=http-server

gcloud compute firewall-rules create allow-https \
  --allow tcp:443 --target-tags=https-server
```

### 3. Conectar y configurar servidor

```bash
# SSH al servidor
gcloud compute ssh vamospues-api --zone=us-central1-a

# Instalar PHP 8.2, Nginx, Composer, Supervisor (igual que AWS)
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis \
  php8.2-intl nginx supervisor git

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo apt install -y certbot python3-certbot-nginx
```

### 4. Desplegar código

```bash
sudo mkdir -p /var/www/vamospues-api
sudo chown $USER:$USER /var/www/vamospues-api
git clone https://github.com/tu-org/vamospues-backend.git /var/www/vamospues-api
cd /var/www/vamospues-api

composer install --no-dev --optimize-autoloader
cp .env.example .env
# Editar .env con los valores de producción

php artisan key:generate
php artisan passport:keys
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link

sudo chown -R www-data:www-data /var/www/vamospues-api
sudo chmod -R 775 /var/www/vamospues-api/storage /var/www/vamospues-api/bootstrap/cache
```

Ver [README.md](README.md) para la configuración completa de Nginx y Supervisor.

---

## Opción B — Cloud Run con Docker

Recomendado para despliegues modernos: sin gestión de servidor, escala a cero cuando no hay tráfico.

### 1. Crear Dockerfile

```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

# Instalar extensiones
RUN apk add --no-cache \
    nginx supervisor curl git zip unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd bcmath zip opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar código
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar configuración de Nginx y Supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh

RUN chmod +x /start.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
CMD ["/start.sh"]
```

```bash
# docker/start.sh
#!/bin/sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php-fpm -D
exec nginx -g 'daemon off;'
```

### 2. Build y push a Container Registry

```bash
# Configurar Docker con GCP
gcloud auth configure-docker

# Build y push
docker build -t gcr.io/vamospues-prod/api:latest .
docker push gcr.io/vamospues-prod/api:latest
```

### 3. Desplegar en Cloud Run

```bash
gcloud run deploy vamospues-api \
  --image gcr.io/vamospues-prod/api:latest \
  --region us-central1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 512Mi \
  --cpu 1 \
  --min-instances 1 \
  --max-instances 10 \
  --set-env-vars APP_ENV=production,APP_DEBUG=false \
  --set-secrets APP_KEY=app-key:latest,DB_PASSWORD=db-password:latest
```

### 4. Variables secretas con Secret Manager

```bash
# Crear secretos
echo -n "base64:..." | gcloud secrets create app-key --data-file=-
echo -n "mi-password-segura" | gcloud secrets create db-password --data-file=-

# Dar acceso al service account de Cloud Run
gcloud secrets add-iam-policy-binding app-key \
  --member="serviceAccount:PROJECT_NUMBER-compute@developer.gserviceaccount.com" \
  --role="roles/secretmanager.secretAccessor"
```

---

## Base de datos — Cloud SQL

```bash
# Crear instancia Cloud SQL (MySQL 8.0)
gcloud sql instances create vamospues-db \
  --database-version=MYSQL_8_0 \
  --tier=db-f1-micro \
  --region=us-central1 \
  --no-assign-ip \
  --network=default

# Crear base de datos
gcloud sql databases create vamospues --instance=vamospues-db

# Crear usuario
gcloud sql users create vamospues_user \
  --instance=vamospues-db \
  --password="<contraseña-segura>"
```

**Para Compute Engine**, conectar directamente por IP privada:
```ini
DB_HOST=<ip-privada-cloud-sql>
```

**Para Cloud Run**, usar Cloud SQL Auth Proxy:
```bash
# Agregar al comando de deploy:
--set-env-vars CLOUD_SQL_CONNECTION_NAME=vamospues-prod:us-central1:vamospues-db
--add-cloudsql-instances vamospues-prod:us-central1:vamospues-db
```

```ini
DB_HOST=/cloudsql/vamospues-prod:us-central1:vamospues-db
DB_SOCKET=/cloudsql/vamospues-prod:us-central1:vamospues-db
```

---

## Storage — Cloud Storage

Google Cloud Storage es compatible con la API de S3 usando el adaptador `flysystem-aws-s3-v3` con un endpoint personalizado, o usando `flysystem-google-cloud-storage`.

### Opción 1: Adaptador GCS nativo

```bash
composer require superbalist/flysystem-google-storage
```

```ini
# .env
FILESYSTEM_DISK=gcs
GOOGLE_CLOUD_PROJECT_ID=vamospues-prod
GOOGLE_CLOUD_KEY_FILE=/var/www/vamospues-api/storage/app/gcs-key.json
GOOGLE_CLOUD_STORAGE_BUCKET=vamospues-media
```

### Opción 2: Interoperabilidad S3 (más simple)

Cloud Storage tiene un endpoint compatible con S3:

```bash
# Crear bucket
gcloud storage buckets create gs://vamospues-media \
  --location=us-central1 \
  --uniform-bucket-level-access

# Crear HMAC keys (equivalentes a AWS access keys)
gcloud storage hmac create <service-account-email>
```

```ini
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<HMAC_ACCESS_ID>
AWS_SECRET_ACCESS_KEY=<HMAC_SECRET>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=vamospues-media
AWS_ENDPOINT=https://storage.googleapis.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Hacer el bucket público (para URLs de media)

```bash
gcloud storage buckets add-iam-policy-binding gs://vamospues-media \
  --member="allUsers" \
  --role="roles/storage.objectViewer"
```

---

## Variables de entorno GCP

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

# Cloud SQL
DB_CONNECTION=mysql
DB_HOST=<ip-privada-cloud-sql>
DB_PORT=3306
DB_DATABASE=vamospues
DB_USERNAME=vamospues_user
DB_PASSWORD=<contraseña>

# Memorystore Redis (opcional)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=<ip-memorystore>
REDIS_PORT=6379

# Cloud Storage (modo S3 interop)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<hmac-key>
AWS_SECRET_ACCESS_KEY=<hmac-secret>
AWS_BUCKET=vamospues-media
AWS_ENDPOINT=https://storage.googleapis.com

# Mail via SendGrid o SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=<sendgrid-api-key>
```

---

## Dominio y SSL

### Compute Engine

```bash
sudo certbot --nginx -d api.tudominio.com
```

### Cloud Run

Cloud Run provee HTTPS automáticamente con el dominio `*.run.app`. Para dominio propio:

```bash
gcloud run domain-mappings create \
  --service vamospues-api \
  --domain api.tudominio.com \
  --region us-central1
```

Agregar el registro CNAME indicado al DNS de tu dominio.
