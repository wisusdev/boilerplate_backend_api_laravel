# Despliegue en AWS

Arquitectura recomendada: **EC2** (servidor PHP) + **RDS** (base de datos) + **S3** (media/storage) + **SES** (correo) + **ElastiCache** (Redis opcional).

## Índice

1. [Arquitectura](#arquitectura)
2. [Crear infraestructura](#crear-infraestructura)
3. [Configurar el servidor EC2](#configurar-el-servidor-ec2)
4. [Desplegar la aplicación](#desplegar-la-aplicación)
5. [Configurar S3 para media](#configurar-s3-para-media)
6. [Configurar dominio y SSL](#configurar-dominio-y-ssl)
7. [Variables de entorno AWS](#variables-de-entorno-aws)
8. [Alternativa: Elastic Beanstalk](#alternativa-elastic-beanstalk)

---

## Arquitectura

```
Internet
    │
    ▼
Route 53 (DNS)
    │
    ▼
Application Load Balancer  (opcional, para alta disponibilidad)
    │
    ▼
EC2 (Ubuntu 22.04 — t3.small o superior)
    ├── PHP 8.2-FPM + Nginx
    ├── Supervisor (queue workers)
    └── Cron (Laravel scheduler)
    │
    ├──→ RDS MySQL 8.0 (db.t3.micro o superior)
    ├──→ ElastiCache Redis (cache.t3.micro, opcional)
    ├──→ S3 (media uploads)
    └──→ SES (envío de correos)
```

---

## Crear infraestructura

### 1. Instancia EC2

```bash
# Desde la consola AWS o CLI:
aws ec2 run-instances \
  --image-id ami-0c02fb55956c7d316 \  # Ubuntu 22.04 LTS (us-east-1)
  --instance-type t3.small \
  --key-name mi-clave-ssh \
  --security-group-ids sg-xxxxxxxxxx \
  --subnet-id subnet-xxxxxxxxxx \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=vamospues-api}]'
```

**Security Group — reglas de entrada:**
| Puerto | Protocolo | Origen |
|--------|-----------|--------|
| 22 | TCP | Tu IP (SSH) |
| 80 | TCP | 0.0.0.0/0 |
| 443 | TCP | 0.0.0.0/0 |

### 2. Base de datos RDS

```bash
aws rds create-db-instance \
  --db-instance-identifier vamospues-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --master-username vamospues_user \
  --master-user-password "<contraseña-segura>" \
  --allocated-storage 20 \
  --db-name vamospues \
  --no-publicly-accessible \
  --vpc-security-group-ids sg-xxxxxxxxxx
```

> RDS debe estar en el mismo VPC que EC2 y no ser accesible desde internet. El Security Group de RDS debe permitir tráfico en el puerto 3306 únicamente desde el Security Group de EC2.

### 3. Bucket S3 para media

```bash
# Crear bucket
aws s3api create-bucket \
  --bucket vamospues-media \
  --region us-east-1

# Configurar CORS (para acceso desde el frontend)
aws s3api put-bucket-cors \
  --bucket vamospues-media \
  --cors-configuration '{
    "CORSRules": [{
      "AllowedOrigins": ["https://tudominio.com"],
      "AllowedMethods": ["GET"],
      "AllowedHeaders": ["*"],
      "MaxAgeSeconds": 3000
    }]
  }'

# Bloquear acceso público al bucket (se accede por URL firmada o CloudFront)
aws s3api put-public-access-block \
  --bucket vamospues-media \
  --public-access-block-configuration "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

**Alternativa:** Hacer el bucket público para URLs directas (más simple, menos seguro):
```bash
# Política de bucket para lectura pública
aws s3api put-bucket-policy --bucket vamospues-media --policy '{
  "Version": "2012-10-17",
  "Statement": [{
    "Sid": "PublicReadGetObject",
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::vamospues-media/*"
  }]
}'
```

### 4. Usuario IAM para la app

```bash
# Crear usuario
aws iam create-user --user-name vamospues-app

# Crear política con permisos mínimos a S3
aws iam put-user-policy \
  --user-name vamospues-app \
  --policy-name s3-media-access \
  --policy-document '{
    "Version": "2012-10-17",
    "Statement": [{
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::vamospues-media/*"
    }, {
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::vamospues-media"
    }]
  }'

# Crear access key
aws iam create-access-key --user-name vamospues-app
# Guardar AccessKeyId y SecretAccessKey
```

---

## Configurar el servidor EC2

```bash
# Conectar al servidor
ssh -i mi-clave-ssh.pem ubuntu@<IP-EC2>

# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.2 y extensiones
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-pdo \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
  php8.2-gd php8.2-bcmath php8.2-redis php8.2-intl \
  php8.2-tokenizer php8.2-dom

# Instalar Nginx
sudo apt install -y nginx

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Supervisor
sudo apt install -y supervisor

# Instalar Certbot (SSL)
sudo apt install -y certbot python3-certbot-nginx

# Instalar MySQL cliente (solo cliente, la DB está en RDS)
sudo apt install -y mysql-client-8.0

# Instalar Git
sudo apt install -y git
```

---

## Desplegar la aplicación

```bash
# Crear directorio de la app
sudo mkdir -p /var/www/vamospues-api
sudo chown ubuntu:ubuntu /var/www/vamospues-api

# Clonar repositorio
git clone https://github.com/tu-org/vamospues-backend.git /var/www/vamospues-api

cd /var/www/vamospues-api

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Crear archivo de entorno
cp .env.example .env
nano .env   # Configurar variables (ver sección Variables de entorno)

# Generar clave de la app
php artisan key:generate

# Generar claves Passport
php artisan passport:keys

# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders
php artisan db:seed --force

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
```

### Configurar Nginx

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

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/vamospues-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Configurar Supervisor

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
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start vamospues-worker:*
```

### Cron

```bash
sudo crontab -u www-data -e
# Agregar:
* * * * * cd /var/www/vamospues-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Configurar S3 para media

En el `.env` del backend:

```ini
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<AccessKeyId del IAM>
AWS_SECRET_ACCESS_KEY=<SecretAccessKey del IAM>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=vamospues-media
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Verificar que el paquete de S3 esté instalado:

```bash
composer require league/flysystem-aws-s3-v3
```

---

## Configurar dominio y SSL

```bash
# Obtener certificado SSL con Let's Encrypt
sudo certbot --nginx -d api.tudominio.com

# Certbot configura Nginx automáticamente para HTTPS
# Los certificados se renuevan solos con:
sudo systemctl status certbot.timer
```

---

## Variables de entorno AWS

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=<endpoint-rds>.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=vamospues
DB_USERNAME=vamospues_user
DB_PASSWORD=<contraseña>

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=cookie
FILESYSTEM_DISK=s3

REDIS_HOST=<endpoint-elasticache>.cache.amazonaws.com
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=ses
AWS_DEFAULT_REGION=us-east-1
# SES usa las mismas credenciales IAM

AWS_ACCESS_KEY_ID=<iam-key>
AWS_SECRET_ACCESS_KEY=<iam-secret>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=vamospues-media
```

> Si no usas ElastiCache, usa `CACHE_DRIVER=file` y `QUEUE_CONNECTION=database` (requiere `php artisan queue:table && php artisan migrate`).

---

## Despliegue continuo (CI/CD básico)

Script de deploy para ejecutar desde GitHub Actions o manualmente:

```bash
#!/bin/bash
# /var/www/vamospues-api/deploy.sh

set -e

cd /var/www/vamospues-api

git pull origin main

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo supervisorctl restart vamospues-worker:*

echo "Deploy completado: $(date)"
```

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## Alternativa: Elastic Beanstalk

Para menor gestión de infraestructura, usar **Elastic Beanstalk con PHP 8.2**:

```bash
# Instalar EB CLI
pip install awsebcli

# Inicializar proyecto
eb init vamospues-api --region us-east-1 --platform php-8.2

# Crear entorno
eb create vamospues-prod --instance-type t3.small

# Desplegar
eb deploy

# Configurar variables de entorno
eb setenv APP_ENV=production APP_KEY=base64:... DB_HOST=...
```

Crear `.ebextensions/nginx.config` para reglas de Nginx y `.ebextensions/php.config` para configuración de PHP.
