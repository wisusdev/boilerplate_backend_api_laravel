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
php artisan passport:client --personal
```

## Frontend

[Version Frontend Mobile (Flutter)](https://github.com/wisusdev/boilerplate_frontend_mobile_flutter)

[Version Frontend Web (Angular)](https://github.com/wisusdev/boilerplate_frontend_web_angular)
