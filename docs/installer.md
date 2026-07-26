# Instalador (estilo WordPress)

El instalador crea el **primer usuario administrador** y guarda los datos
básicos del sitio. Sustituye al antiguo administrador con credenciales fijas del
seeder: una instalación nueva queda **limpia** y el admin se crea de forma
interactiva, como el instalador de 5 minutos de WordPress.

## Instalación limpia

`php artisan db:seed` (a través de `DatabaseSeeder`) solo siembra la
infraestructura base:

- `SettingSeeder` — ajustes por defecto (`app`, `payment_gateway`, …). El
  bloque `app` incluye `"installed": false`.
- `PermissionSeeder` — permisos.
- `RoleSeeder` — roles `user` y `admin` (el rol `admin` recibe todos los
  permisos).

**No** se crea ningún administrador ni datos de demostración. Los datos de demo
quedan disponibles bajo demanda:

```bash
php artisan db:seed --class=UserSeeder          # usuarios de prueba
php artisan db:seed --class=TravelModuleSeeder  # tours, vehículos, cupones
php artisan db:seed --class=StockImageSeeder    # imágenes de stock
```

## Verificación de requisitos (pre-flight)

Antes de crear el administrador, el instalador comprueba que el entorno cumple
lo necesario para ejecutar la aplicación. Cada comprobación tiene un estado:

- `ok` — correcto.
- `warning` — no bloquea, pero conviene revisar (p. ej. extensiones recomendadas).
- `error` — bloquea si es un requisito **obligatorio** (`required = true`).

La instalación se considera posible (`satisfied = true`) cuando **ninguna**
comprobación obligatoria está en `error`. Cada comprobación se clasifica en un
`group` para poder mostrarla en un paso distinto del wizard:

**`project` — Requisitos del proyecto**

| Comprobación | Obligatorio | Detalle |
|--------------|:----------:|---------|
| Versión de PHP | ✔ | `>= 8.3.0` (alineado con `composer.json`) |
| Extensiones PHP | ✔ | `ctype, curl, dom, fileinfo, filter, gd, hash, mbstring, openssl, pcre, pdo, session, tokenizer, xml` |
| Extensiones recomendadas | — | `bcmath, intl, zip` (solo `warning` si faltan) |
| Dependencias de Composer | ✔ | `vendor/autoload.php` presente (`composer install`) |

**`permissions` — Permisos**

| Comprobación | Obligatorio | Detalle |
|--------------|:----------:|---------|
| Permisos de escritura | ✔ | `storage/`, `storage/framework/*`, `storage/logs`, `bootstrap/cache` |

Cada directorio reporta su **modo octal actual** (p. ej. `0755 · solo lectura`) y
el **recomendado** en `expected` (`0775 · con escritura`). El wizard muestra una
nota con la convención recomendada (`chmod -R 0775 storage bootstrap/cache` y
propiedad del usuario del servidor web) y, por directorio, la línea
`recomendado: …` **solo si el valor actual difiere del recomendado** (no se
duplica cuando ya coincide). El octal recomendado está en
`InstallRequirementsService::RECOMMENDED_DIR_PERMISSION`.

**`environment` — Entorno**

| Comprobación | Obligatorio | Detalle |
|--------------|:----------:|---------|
| Clave de la app | ✔ | `APP_KEY` definida (`php artisan key:generate`) |
| Conexión a la base de datos | ✔ | `DB::connection()->getPdo()` (credenciales `DB_*`) |
| Migraciones ejecutadas | ✔ | tablas `users` y `migrations` presentes (`php artisan migrate`) |

Cada comprobación fallida incluye un `hint` con la acción sugerida.

- **Wizard web:** los requisitos se reparten en **tres pasos** (`Requisitos del
  proyecto` → `Permisos` → `Entorno`), cada uno con su lista de estado y un botón
  *Volver a comprobar*. *Continuar* se bloquea mientras el grupo del paso actual
  tenga algún requisito obligatorio en `error`.
- **Comando artisan:** imprime una tabla antes de instalar y aborta si falta un
  requisito obligatorio. `--skip-requirements` la omite bajo tu responsabilidad.
- **Endpoint:** el `POST /api/v1/install` también revalida y responde `422`
  (`app.requirementsNotMet`) con los requisitos fallidos si el entorno no cumple.

## Estado de instalación

La aplicación se considera **instalada** cuando existe al menos un usuario con
rol `admin` o `super-admin`. No depende de un flag frágil: el propio hecho de
que exista un administrador es la fuente de verdad (el instalador además marca
`app.installed = true` como registro).

## Datos base (autosuficiente)

El instalador **siembra los datos base de forma idempotente** antes de crear el
administrador: permisos, roles (`user`, `admin`, `guia`) y categorías de gasto;
los settings solo si la tabla está vacía. Además, refresca la caché de permisos
de Spatie y otorga **todos los permisos** al rol `admin`.

Por eso **`php artisan migrate` + el instalador es suficiente**: no hace falta
ejecutar `php artisan db:seed` antes. (Si se ejecuta, no pasa nada: los seeders
base son idempotentes.) Esto evita el error *"There is no permission named
`users:index`"* que aparecía si se instalaba sin sembrar los permisos.

## Endpoints

Públicos mientras la app no esté instalada. El `POST` se **autobloquea** con
`409` una vez que existe un administrador.

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET`  | `/api/v1/install/status` | Devuelve `{ "data": { "attributes": { "installed": boolean } } }` |
| `GET`  | `/api/v1/install/requirements` | Informe de requisitos: `{ "data": { "attributes": { "satisfied": boolean, "checks": [...] } } }` |
| `POST` | `/api/v1/install` | Crea el admin + datos del sitio (revalida requisitos → `422` si no cumplen) |

> Estas rutas **no** usan el formato JSON:API: aceptan JSON plano
> (`Content-Type: application/json`).

### Cuerpo de `POST /api/v1/install`

```json
{
  "first_name": "Jesus",
  "last_name": "Avelar",
  "email": "admin@tu-dominio.com",
  "password": "tu-password",
  "password_confirmation": "tu-password",
  "site_name": "Cusgo Adventures",
  "contact_email": "info@tu-dominio.com",
  "currency": "USD",
  "timezone": "America/El_Salvador"
}
```

| Campo | Reglas |
|-------|--------|
| `first_name`, `last_name` | requerido, string, máx. 255 |
| `email` | requerido, email, único en `users` |
| `password` | requerido, mín. 8, `confirmed` (requiere `password_confirmation`) |
| `site_name` | opcional → `app.name` |
| `contact_email` | opcional, email → `app.email` |
| `currency` | opcional → `payment_gateway.currency` (+ símbolo) |
| `timezone` | opcional, zona horaria válida → `app.timezone` |

Respuesta `201`:

```json
{ "data": { "type": "install", "attributes": {
  "status": true, "installed": true, "message": "message.installed",
  "email": "admin@tu-dominio.com"
} } }
```

## Wizard web (frontend)

En el frontend, el instalador vive en la ruta **`/install`** (wizard de dos
pasos: cuenta de administrador → datos del sitio). Mientras la app no esté
instalada, las rutas de autenticación redirigen allí. Al finalizar, inicia
sesión automáticamente con el admin recién creado y entra al panel.

## Comando artisan

Respaldo del wizard, útil en despliegues por consola y CI:

```bash
php artisan app:install \
  --first-name="Jesus" --last-name="Avelar" \
  --email="admin@tu-dominio.com" --password="tu-password" \
  --site-name="Cusgo Adventures" --contact-email="info@tu-dominio.com" \
  --currency=USD --timezone=America/El_Salvador
```

Sin opciones, pregunta los datos de forma interactiva. `--force` permite crear
otro administrador aunque ya exista uno (no elimina datos).
`--skip-requirements` omite la verificación de requisitos previos del entorno.

## Componentes

| Pieza | Ubicación |
|-------|-----------|
| Lógica | `app/Services/InstallService.php` |
| Requisitos (pre-flight) | `app/Services/InstallRequirementsService.php` |
| Controlador | `app/Http/Controllers/Api/Base/InstallController.php` |
| Validación | `app/Http/Requests/InstallRequest.php` |
| Comando | `app/Console/Commands/InstallCommand.php` |
| Rutas | `routes/api.php` (grupo `install`) |
| Tests | `tests/Feature/Base/InstallTest.php` |
