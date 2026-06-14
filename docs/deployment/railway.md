# Despliegue en Railway / Render

Plataformas PaaS que eliminan la gestión de servidores. Ideales para proyectos en etapa de MVP o equipos pequeños. Sin configuración de Nginx ni Supervisor.

## Índice

1. [Railway](#railway)
2. [Render](#render)
3. [Variables de entorno comunes](#variables-de-entorno-comunes)

---

## Railway

Railway detecta Laravel automáticamente y provisiona base de datos, Redis y workers desde la misma interfaz.

### Prerrequisitos

```bash
npm install -g @railway/cli
railway login
```

### 1. Inicializar proyecto

```bash
cd vamospues-backend
railway init
# Seleccionar: Create new project → vamospues-api
```

### 2. Agregar servicios desde la consola

En **railway.app → tu proyecto**, agregar:
- **MySQL** (plugin oficial) — crea una DB automáticamente
- **Redis** (plugin oficial) — opcional, para colas

Railway inyecta automáticamente las variables `MYSQL_*` y `REDIS_*` en los servicios.

### 3. Conectar repositorio GitHub

1. En el servicio principal, ir a **Settings → Source**
2. Conectar repositorio de GitHub
3. Seleccionar branch `main`

Railway detecta PHP y usa Nixpacks como builder.

### 4. Configurar Procfile

```
# Procfile
web: vendor/bin/heroku-php-nginx -C railway-nginx.conf public/
worker: php artisan queue:work --sleep=3 --tries=3
```

```nginx
# railway-nginx.conf
client_max_body_size 50M;
```

### 5. Configurar variables de entorno

En **Service → Variables**, agregar:

```
APP_KEY          = (generar con: php artisan key:generate --show)
APP_ENV          = production
APP_DEBUG        = false
APP_URL          = https://<tu-servicio>.railway.app
APP_FRONT_URL    = https://tudominio.com
FILESYSTEM_DISK  = local   # O 's3' si usas un bucket externo
QUEUE_CONNECTION = redis
```

Railway provee las variables de DB y Redis automáticamente como:
```
${{MySQL.MYSQL_URL}}
${{Redis.REDIS_URL}}
```

Referenciarlas en las variables del servicio web:
```
DATABASE_URL = ${{MySQL.MYSQL_URL}}
REDIS_URL    = ${{Redis.REDIS_URL}}
```

Para usar el formato individual de Laravel, agregar manualmente:
```
DB_CONNECTION = mysql
DB_HOST       = ${{MySQL.MYSQL_HOST}}
DB_PORT       = ${{MySQL.MYSQL_PORT}}
DB_DATABASE   = ${{MySQL.MYSQL_DATABASE}}
DB_USERNAME   = ${{MySQL.MYSQL_USER}}
DB_PASSWORD   = ${{MySQL.MYSQL_PASSWORD}}
REDIS_HOST    = ${{Redis.REDIS_HOST}}
REDIS_PORT    = ${{Redis.REDIS_PORT}}
REDIS_PASSWORD = ${{Redis.REDIS_PASSWORD}}
```

### 6. Migraciones como Release Command

En **Service → Settings → Deploy → Custom Start Command**:

O crear `railway.json` en la raíz:

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "NIXPACKS"
  },
  "deploy": {
    "releaseCommand": "php artisan migrate --force",
    "restartPolicyType": "ON_FAILURE"
  }
}
```

### 7. Desplegar

```bash
railway up
```

### 8. Ejecutar seeders (primera vez)

```bash
railway run php artisan db:seed --force
railway run php artisan passport:keys
```

### 9. Dominio personalizado

En **Service → Settings → Networking → Custom Domain**:
1. Agregar `api.tudominio.com`
2. Crear registro CNAME apuntando al dominio de Railway
3. Railway provee SSL automáticamente

---

## Render

Alternativa a Railway con soporte para **Web Services**, **Background Workers** y **Cron Jobs** nativos.

### 1. Crear cuenta y conectar repo

1. Ir a render.com y crear cuenta
2. **New → Web Service**
3. Conectar repositorio de GitHub
4. Configurar:
   - **Runtime:** PHP
   - **Build Command:** `composer install --no-dev --optimize-autoloader && php artisan key:generate && php artisan migrate --force`
   - **Start Command:** `php artisan serve --host 0.0.0.0 --port $PORT`

> Para mejor rendimiento, usar Nginx en lugar de `artisan serve`. Crear un `render.yaml`:

### 2. Render Blueprint (`render.yaml`)

```yaml
# render.yaml (en la raíz del proyecto)
services:
  - type: web
    name: vamospues-api
    runtime: php
    plan: starter
    buildCommand: |
      composer install --no-dev --optimize-autoloader
      php artisan key:generate
      php artisan migrate --force
      php artisan config:cache
      php artisan route:cache
    startCommand: php -S 0.0.0.0:$PORT -t public
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - fromGroup: vamospues-secrets

  - type: worker
    name: vamospues-queue
    runtime: php
    plan: starter
    buildCommand: composer install --no-dev --optimize-autoloader
    startCommand: php artisan queue:work --sleep=3 --tries=3
    envVars:
      - fromGroup: vamospues-secrets

  - type: cron
    name: vamospues-scheduler
    runtime: php
    plan: starter
    schedule: "* * * * *"
    buildCommand: composer install --no-dev --optimize-autoloader
    startCommand: php artisan schedule:run

databases:
  - name: vamospues-db
    databaseName: vamospues
    user: vamospues_user
    plan: free
```

### 3. Configurar variables en Render

En **Environment → Environment Groups**, crear grupo `vamospues-secrets`:

```
APP_KEY              = base64:...
DB_CONNECTION        = mysql
DB_HOST              = <render-db-host>
DB_PORT              = 3306
DB_DATABASE          = vamospues
DB_USERNAME          = vamospues_user
DB_PASSWORD          = <password>
FILESYSTEM_DISK      = s3
AWS_ACCESS_KEY_ID    = ...
AWS_SECRET_ACCESS_KEY = ...
```

### 4. Ejecutar desde shell de Render

```bash
# En Render → Service → Shell
php artisan db:seed --force
php artisan passport:keys
```

### 5. Dominio personalizado

En **Service → Settings → Custom Domains**:
1. Agregar `api.tudominio.com`
2. Crear registro CNAME apuntando a `<servicio>.onrender.com`

---

## Variables de entorno comunes

```ini
APP_NAME="VamosPues"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_FRONT_URL=https://tudominio.com

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=cookie

LIMIT_AUTH_DEVICES=3

# Pagos (modo producción)
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

## Comparativa Railway vs. Render

| Característica | Railway | Render |
|----------------|---------|--------|
| Precio base | ~$5/mes (hobby) | Free tier disponible |
| Free tier | No (solo trial) | Sí (con sleep en inactividad) |
| MySQL incluido | Sí (plugin) | Sí (servicio separado) |
| Redis incluido | Sí (plugin) | Sí (servicio separado) |
| Workers nativos | Via Procfile | Sí (tipo worker nativo) |
| Cron nativo | Via Procfile + cron | Sí (tipo cron nativo) |
| Deploy automático | Sí (push a GitHub) | Sí (push a GitHub) |
| Shell interactivo | Sí | Sí |
| Dominio HTTPS gratis | Sí | Sí |
