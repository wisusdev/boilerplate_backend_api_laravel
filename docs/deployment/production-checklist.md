# Guía para pasar el proyecto a producción

Lista de verificación de **go-live** para el proyecto completo:

- **Backend** — API Laravel (`vamosPues_backend_api_laravel`)
- **Frontend** — SPA React + Vite (`vamosPues_frontend_web_react`)

> Esta guía es **agnóstica del proveedor**. Para pasos específicos de infraestructura (servidor, base de datos, Nginx, SSL) consulta las guías por proveedor en esta misma carpeta: [aws.md](aws.md), [gcp.md](gcp.md), [digitalocean.md](digitalocean.md), [railway.md](railway.md), [vps.md](vps.md).

---

## 0. Arquitectura en producción

```
[ Navegador ] ──HTTPS──> [ Frontend SPA (estático/CDN) ]
                                   │  fetch  VITE_API_BASE_URL
                                   ▼
                         [ API Laravel (PHP-FPM + Nginx) ]
                                   │
                    [ MySQL/PostgreSQL ] [ Redis ] [ Storage ] [ SMTP ]
```

- El frontend se compila a archivos estáticos (`dist/`) y se sirve desde un host estático / CDN / Nginx.
- El backend es un API JSON:API autenticado con Laravel Passport (OAuth2, tokens Bearer).
- Ambos en **HTTPS** y en dominios/subdominios definidos (p. ej. `app.midominio.com` y `api.midominio.com`).

---

## 1. Backend — checklist de despliegue

### 1.1 Variables de entorno (`.env`)

| Variable | Valor en producción | Notas |
|----------|---------------------|-------|
| `APP_ENV` | `production` | **Obligatorio** |
| `APP_DEBUG` | `false` | **Crítico** — nunca `true` en producción |
| `APP_KEY` | (generada) | `php artisan key:generate` |
| `APP_URL` | `https://api.midominio.com` | URL pública del API |
| `APP_FRONT_URL` | `https://app.midominio.com` | Se usa en los enlaces de los correos (verificación, reset) |
| `DB_CONNECTION` / `DB_*` | credenciales reales | MySQL 8+ o PostgreSQL 15+ |
| `CACHE_DRIVER` | `redis` | Recomendado (los rate limiters usan el cache) |
| `QUEUE_CONNECTION` | `redis` (o `database`) | Necesario para enviar correos en segundo plano |
| `SESSION_DRIVER` | `redis` | |
| `MAIL_MAILER` | `smtp` (o Mailgun/SES) | **Crítico**: sin esto NO llegan correos de verificación, reset ni del formulario de contacto |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` | credenciales SMTP reales | |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | remitente verificado | |

> ⚠️ En desarrollo `MAIL_MAILER` suele estar en `log` (los correos quedan en `storage/logs/laravel.log`). En **producción** debe apuntar a un SMTP/servicio real o las notificaciones no se entregarán.

### 1.2 Pasos de despliegue

```bash
# 1. Dependencias (sin dev, optimizado)
composer install --no-dev --optimize-autoloader

# 2. Clave de la app (si es primer despliegue)
php artisan key:generate

# 3. Llaves de Passport (OAuth) — solo el primer despliegue
php artisan passport:keys
# (o `php artisan passport:install` si aún no hay clientes)

# 4. Migraciones (sin interacción)
php artisan migrate --force

# 5. Enlace de almacenamiento público (imágenes de tours/galería)
php artisan storage:link

# 6. Cachés de producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 1.3 Seeders (con cuidado)

- **No** ejecutes `migrate:fresh --seed` en producción: **borra toda la base de datos**.
- Para datos base mínimos (roles, permisos, moneda) usa seeders idempotentes específicos, p. ej.:
  ```bash
  php artisan db:seed --class=RolesAndPermissionsSeeder --force
  ```
- `StockImageSeeder` y `TravelModuleSeeder` generan **datos de demo** (50+ tours, imágenes descargadas de internet). Son para entornos de **prueba**, no para producción.

### 1.4 Workers y tareas programadas

- **Cola** (correos/notificaciones): mantener un worker vivo con supervisor/systemd:
  ```bash
  php artisan queue:work --tries=3 --timeout=90
  ```
  > Si `QUEUE_CONNECTION=sync`, los correos se envían en la misma petición (más lento). Para el formulario de contacto y verificación conviene `redis`/`database` + worker.
- **Scheduler** (si aplica): `* * * * * php artisan schedule:run` en cron.

### 1.5 Seguridad y CORS

- `APP_DEBUG=false` y `APP_ENV=production`.
- **CORS**: en `config/cors.php` permitir el origen del frontend (`https://app.midominio.com`), no `*`.
- **Rate limiting** activo (se desactiva solo en entorno `testing`): protege login, registro, recuperación, reenvío de verificación y el formulario de contacto. Verifica que `CACHE_DRIVER` esté configurado.
- Servir todo por **HTTPS** (HSTS recomendado).
- Permisos de archivos: `storage/` y `bootstrap/cache/` escribibles por el usuario web.
- **No** commitear `.env` ni las llaves de `storage/oauth-*.key` (Passport).
- Programar **backups** de base de datos y del directorio `storage/app`.

### 1.6 Configuración post-despliegue (panel admin)

Tras desplegar, un administrador debe configurar en **Configuración**:
- **Correo de contacto (público)** y **Correos para notificaciones de contacto** (`inquiry_notification_emails` — uno o varios separados por coma; reciben los mensajes del formulario).
- **Pasarelas de pago** con credenciales **live** (PayPal, Stripe, Wompi) y activar las que se usen.
- **Login social** (Google/Facebook) si se usa.
- **DTE / Facturación electrónica** (certificado y ambiente `production`) si aplica — ver [../dte-facturacion-electronica.md](../dte-facturacion-electronica.md).

---

## 2. Frontend — checklist de despliegue

### 2.1 Variables de entorno

| Variable | Valor en producción |
|----------|---------------------|
| `VITE_API_BASE_URL` | `https://api.midominio.com/api/v1` |

> Las variables `VITE_*` se **incrustan en el build**: hay que definirlas **antes** de `npm run build` (no se leen en runtime).

### 2.2 Build

```bash
npm ci
npm run build      # genera dist/
```

### 2.3 Servir el SPA

- Publicar el contenido de `dist/` en un host estático / CDN / Nginx.
- **Fallback SPA obligatorio**: como usa enrutamiento del lado del cliente (React Router), **todas** las rutas deben servir `index.html` (si no, recargar `/tours` o `/profile` da 404).
  - Nginx:
    ```nginx
    location / {
      try_files $uri $uri/ /index.html;
    }
    ```
  - Netlify: `_redirects` con `/*  /index.html  200`.
- Cabeceras de caché: `index.html` sin caché; los assets con hash (`/assets/*`) con caché larga (`immutable`).
- Servir por **HTTPS**.

---

## 3. Verificación post-despliegue (smoke test)

- [ ] El frontend carga en `https://app.midominio.com` y navega entre rutas (recargar `/tours` no da 404).
- [ ] El catálogo de tours/transporte carga datos del API (paginación y filtros funcionan).
- [ ] Registro → llega el correo de verificación; el enlace verifica la cuenta.
- [ ] Login y acceso a `/profile`.
- [ ] "Olvidé mi contraseña" → llega el correo de reset; el reset funciona y notifica.
- [ ] Enviar el **formulario de contacto** → llega a los correos configurados en `inquiry_notification_emails`.
- [ ] Un flujo de **pago** de prueba con la pasarela en modo live (monto mínimo) o sandbox controlado.
- [ ] Panel admin accesible solo con rol admin/super-admin.
- [ ] Probar que el **rate limit** responde (varios logins fallidos seguidos → HTTP 429 con mensaje claro).
- [ ] `APP_DEBUG=false` (forzar un error y confirmar que NO muestra el stack trace).

---

## 4. Calidad antes de publicar

```bash
# Backend
php artisan test          # suite completa en sqlite :memory: (no toca tu BD)

# Frontend
npm run test:run          # Vitest
npm run build             # debe compilar sin errores
```

> Casos de prueba manuales (QA): ver la carpeta `quality_assurance/` del repo frontend.

---

## 5. Rollback

- **Backend**: desplegar el commit/tag anterior y, si una migración rompió algo, `php artisan migrate:rollback` (solo si la migración es reversible). Mantener backup de BD previo al deploy.
- **Frontend**: republicar el `dist/` del build anterior (versionar/guardar artefactos de build).
- Limpiar cachés tras revertir backend: `php artisan optimize:clear`.
