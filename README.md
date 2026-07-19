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

### Passport install

```bash
php artisan passport:install
php artisan passport:client --personal --name=local --provider=users --no-interaction
```

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
