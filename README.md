# Backend

## Installation

```bash
git clone https://github.com/wisusdev/boilerplate_backend_api_laravel.git
cd boilerplate_backend_api_laravel
```

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

> **Instalación limpia:** `db:seed` solo siembra la infraestructura base
> (settings, permisos, roles y categorías de gasto). **No** crea un administrador
> con credenciales fijas. El primer administrador se crea con el instalador (ver
> abajo). El paso `db:seed` es **opcional**: el instalador siembra los datos base
> de forma idempotente, así que `migrate` + instalador es suficiente.
>
> Datos de demostración opcionales (solo desarrollo):
> ```bash
> php artisan db:seed --class=UserSeeder          # usuarios de prueba
> php artisan db:seed --class=TravelModuleSeeder  # tours, vehículos, cupones
> php artisan db:seed --class=StockImageSeeder    # imágenes de stock
> ```

### Passport install

```bash
php artisan passport:install
php artisan passport:client --personal --name=local --provider=users --no-interaction
```

## Instalador (estilo WordPress)

Crea el **primer administrador** y guarda los datos del sitio. Se autobloquea
una vez que ya existe un administrador. Dos formas:

- **Wizard web:** abre el frontend en `/install`. Mientras no exista un
  administrador, cualquier ruta de autenticación redirige allí. Al finalizar,
  inicia sesión automáticamente y entra al panel.
- **Consola** (respaldo / CI):

  ```bash
  php artisan app:install \
    --first-name="Jesus" --last-name="Avelar" \
    --email="admin@tu-dominio.com" --password="tu-password" \
    --site-name="Cusgo Adventures" --contact-email="info@tu-dominio.com" \
    --currency=USD --timezone=America/El_Salvador
  ```

  Sin opciones, el comando pregunta los datos de forma interactiva. Usa
  `--force` para forzar la creación de otro administrador aunque ya exista uno.

Endpoints (públicos mientras no esté instalada; el POST responde `409` una vez
instalada):

| Método | Ruta                    | Descripción                        |
|--------|-------------------------|------------------------------------|
| GET    | `/api/v1/install/status`| `{ installed: boolean }`           |
| POST   | `/api/v1/install`       | Crea el admin + datos del sitio    |

## Módulos de negocio

La API de turismo (tours, transporte, reservas, reseñas de producto y
suscriptores a ofertas) está documentada en **[doc/MODULES.md](doc/MODULES.md)**:
campos, reglas de precio/oferta, tarifas de servicio, políticas de reserva,
reseñas con compra verificada, módulo de leads (con correos y toggle
configurable) y ajustes.

Tests: `php artisan test` (cobertura de los módulos en `tests/Feature/Travel` y
`tests/Unit/Services/Booking`).

## Frontend

[Version Frontend Mobile (Flutter)](https://github.com/wisusdev/boilerplate_frontend_mobile_flutter)

[Version Frontend Web (Angular)](https://github.com/wisusdev/boilerplate_frontend_web_angular)
