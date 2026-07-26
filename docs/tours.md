# Módulo de Tours

Gestión del catálogo de experiencias de viaje. Incluye tours, categorías, disponibilidad por fecha y reseñas de clientes.

## Índice

1. [Modelos](#modelos)
2. [Tours — API](#tours--api)
3. [Categorías — API](#categorías--api)
4. [Disponibilidad](#disponibilidad)
5. [Reseñas — API](#reseñas--api)
6. [Gestión de imágenes](#gestión-de-imágenes)

---

## Modelos

### `Tour`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `title` | string | Título del tour |
| `description` | text | Descripción completa |
| `price` | decimal(12,2) | Precio por persona. **No hay precio de oferta**: los descuentos provienen solo de los cupones que el cliente aplica al reservar (ver [coupons.md](coupons.md)) |
| `pricing_tiers` | json array nullable | Tarifas de grupo escalonadas: `[{min_pax, discount_percent}]`. Se aplica el tramo con mayor `min_pax ≤ pax` |
| `service_fees` | json array nullable | Add-ons opcionales: `[{name, amount, calc}]` con `calc` = `fixed` \| `per_person` |
| `vehicle_options` | json array nullable | Opciones de vehículo de paga (hasta 3): `[{name, surcharge}]`. El cliente ve además la opción gratuita "Sin vehículo" |
| `booking_sections` | json object nullable | Visibilidad de secciones del flujo de reserva: `{vehicle, pickup, coupon, fare}` (bool). Ausente = todas visibles |
| `max_capacity` | integer | Capacidad máxima por fecha |
| `location` | string | Ubicación o destino |
| `category_id` | bigint FK → tour_categories nullable | Categoría normalizada (`nullOnDelete`). Reemplaza al antiguo campo de texto `category`. |
| `currency_code` | char(3) | Moneda (ISO 4217). **Solo lectura**: refleja la moneda GLOBAL del sitio; ya no se configura por tour (ver [settings.md](settings.md)) |
| `itinerary` | json array | Lista de pasos del itinerario |
| `highlights` | json array | Puntos destacados del tour |
| `map_url` | string nullable | URL de mapa embebido (Google Maps) |
| `map_markers` | json array | Coordenadas para el mapa interactivo |
| `faqs` | json array | Preguntas frecuentes |
| `is_active` | boolean | Visible en el catálogo público |

> **Configuración global (no por tour):** la **moneda**, la **antelación mínima
> de reserva** y la **ventana de cancelación** ya no se configuran por tour: son
> ajustes GLOBALES del sitio que aplican por igual a todos los tours y vehículos
> (ver [settings.md](settings.md)). Los campos `sale_price`, `child_price`,
> `min_advance_days` y `cancellation_hours` fueron retirados del formulario y de
> la API (sus columnas quedan dormidas por compatibilidad).

**Relaciones:**
- `hasMany(Booking)` — reservas del tour
- `hasMany(TourAvailability)` — capacidad por fecha
- Media collections vía Spatie: `featured_image` (1 imagen), `gallery` (múltiples)

---

### `TourCategory`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `name` | string | Nombre visible |
| `slug` | string unique | Auto-generado desde `name` |
| `color` | string | Color hexadecimal para UI |
| `is_active` | boolean | Visible en el catálogo |
| `sort_order` | integer | Orden de visualización |

---

### `TourAvailability`

Permite sobreescribir la capacidad del tour para una fecha específica o marcar un día como cerrado.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `tour_id` | bigint FK | Tour al que pertenece |
| `available_date` | date | Fecha afectada |
| `capacity_override` | integer nullable | Capacidad para ese día (si es null, usa `tour.max_capacity`) |
| `is_closed` | boolean | Si `true`, no se permiten reservas ese día |

---

### `Review`

Testimonios de clientes mostrados en la landing page.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `name` | string | Nombre del cliente |
| `location` | string | Ciudad/país del cliente |
| `tour` | string | Nombre del tour que realizó |
| `quote` | text | Texto del testimonio |
| `rating` | integer (1–5) | Calificación en estrellas |
| `avatar_url` | string nullable | URL de foto del cliente |
| `is_active` | boolean | Visible en landing |
| `sort_order` | integer | Orden de visualización |

---

## Tours — API

### Rutas públicas (sin autenticación)

```
GET /api/tours              → Listar tours activos
GET /api/tours/{id|slug}    → Ver detalle de un tour
```

> **Nota:** el modelo `Tour` expone un campo `slug` único (generado automáticamente desde el `title` al crear). El endpoint de detalle acepta tanto el **id numérico** como el **slug**: `GET /api/tours/{id|slug}` (p. ej. `/api/v1/tours/volcan-santa-ana-al-amanecer`).

### Rutas de administración (requieren rol admin)

```
POST   /api/tours               → Crear tour
PATCH  /api/tours/{id}          → Actualizar tour
POST   /api/tours/{id}/featured-image  → Subir imagen destacada
POST   /api/tours/{id}/gallery         → Subir imágenes a galería
DELETE /api/tours/{id}/gallery/{media} → Eliminar imagen de galería
```

---

### Listar tours

```
GET /api/tours
```

El listado público soporta paginación, orden y filtros estilo JSON:API.

**Filtros (`filter[campo]=valor`):**

| Param | Descripción |
|-------|-------------|
| `filter[categoryId]` | Filtrar por categoría (ID de `tour_categories`) |
| `filter[priceMin]` | Precio mínimo por persona |
| `filter[priceMax]` | Precio máximo por persona |
| `filter[location]` | Filtrar por ubicación |
| `filter[search]` | Búsqueda libre en **título** y **ubicación** |

**Orden (`sort`):** lista separada por coma; prefijo `-` para descendente. Campos permitidos: `price`, `title`, `max_capacity`, `created_at`. Por defecto (sin `sort`) se ordena por los más recientes. Un campo no permitido devuelve **HTTP 400**.

**Paginación (JSON:API):**

| Param | Descripción |
|-------|-------------|
| `page[size]` | Tamaño de página (default 15) |
| `page[number]` | Número de página |

La respuesta incluye un bloque `meta` con `current_page`, `last_page`, `per_page` y `total`.

**Ejemplos:**

```
GET /api/v1/tours?filter[priceMin]=50&sort=price&page[size]=12
GET /api/v1/tours?filter[categoryId]=1&filter[location]=Santa+Ana&sort=-created_at
GET /api/v1/tours?filter[search]=volcan&sort=price,-max_capacity&page[number]=2&page[size]=10
```

**Response:**
```json
{
  "data": [{
    "type": "tours",
    "id": "1",
    "attributes": {
      "title": "Volcán Santa Ana al amanecer",
      "description": "...",
      "price": "65.00",
      "currency_code": "USD",
      "location": "Santa Ana, El Salvador",
      "category_id": 1,
      "category": "Volcanes",
      "category_color": "#184ca0",
      "max_capacity": 20,
      "is_active": true,
      "featured_image": "https://...",
      "itinerary": ["Salida a las 4am", "Ascenso 3h", "..."],
      "highlights": ["Vista 360°", "Guía certificado"],
      "faqs": [{"question": "...", "answer": "..."}]
    }
  }],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 15,
    "total": 50
  }
}
```

---

### Crear / actualizar tour

```
POST /api/tours
PATCH /api/tours/{id}
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "tours",
    "attributes": {
      "title": "Nombre del tour",
      "description": "Descripción completa",
      "price": 65.00,
      "category_id": 1,
      "max_capacity": 20,
      "location": "Santa Ana",
      "is_active": true,
      "pricing_tiers": [
        {"min_pax": 2, "discount_percent": 10},
        {"min_pax": 5, "discount_percent": 18}
      ],
      "service_fees": [
        {"name": "Seguro de viaje", "amount": 5, "calc": "per_person"}
      ],
      "vehicle_options": [
        {"name": "Sedán privado", "surcharge": 20},
        {"name": "Van con A/C", "surcharge": 35}
      ],
      "booking_sections": {"vehicle": true, "pickup": true, "coupon": true, "fare": true},
      "itinerary": ["Paso 1", "Paso 2"],
      "highlights": ["Punto 1", "Punto 2"],
      "map_markers": [{"lat": 13.849, "lng": -89.623, "label": "Cumbre"}],
      "faqs": [{"question": "¿Qué incluye?", "answer": "Transporte y guía"}]
    }
  }
}
```

---

### Subir imágenes

**Imagen destacada (reemplaza la anterior):**
```
POST /api/tours/{id}/featured-image
Content-Type: multipart/form-data
Authorization: Bearer {token}

image: [archivo]
```

**Galería (permite múltiples archivos):**
```
POST /api/tours/{id}/gallery
Content-Type: multipart/form-data
Authorization: Bearer {token}

images[]: [archivo1]
images[]: [archivo2]
```

**Eliminar imagen de galería:**
```
DELETE /api/tours/{id}/gallery/{media_id}
Authorization: Bearer {token}
```

---

## Categorías — API

### Rutas públicas

```
GET /api/tour-categories
```

Retorna categorías activas ordenadas por `sort_order`.

### Rutas de administración

```
POST   /api/tour-categories
PATCH  /api/tour-categories/{id}
DELETE /api/tour-categories/{id}
```

**Crear categoría:**
```json
{
  "data": {
    "type": "tour-categories",
    "attributes": {
      "name": "Aventura",
      "color": "#FF5733",
      "is_active": true,
      "sort_order": 1
    }
  }
}
```

El `slug` se genera automáticamente desde `name`.

---

## Disponibilidad

> **Nota:** En esta versión la disponibilidad **no se expone como un endpoint REST propio**. Se calcula internamente por `TourAvailabilityService` al momento de crear una reserva de tour.

El servicio `TourAvailabilityService::availableCapacity(Tour $tour, string $date)` determina cuántos cupos quedan para una fecha siguiendo esta lógica:

1. Parte de `tour.max_capacity`.
2. Si existe un registro en `tour_availabilities` para esa fecha:
   - Si `is_closed = true` → capacidad disponible = `0` (no se permiten reservas).
   - Si `capacity_override` no es null → se usa ese valor como capacidad base.
3. Resta la suma de `party_size` de todas las reservas de ese tour y fecha en estado `pending` o `confirmed`.
4. Retorna `max(capacidad - reservado, 0)`.

Esta validación se ejecuta dentro del `TourBookingHandler` con bloqueo pesimista antes de crear la reserva (ver [booking-system.md](booking-system.md)). Si `pax_count` supera la capacidad disponible, la creación falla con error 422.

Los registros de `tour_availabilities` (overrides de capacidad y cierres por fecha) se gestionan actualmente vía seeder/base de datos; no hay endpoint público de administración en esta versión.

---

## Reseñas — API

### Ruta pública

```
GET /api/reviews
```

Retorna reseñas activas ordenadas por `sort_order`.

### Rutas de administración

```
POST   /api/reviews
PATCH  /api/reviews/{id}
DELETE /api/reviews/{id}
```

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/TourController.php` | CRUD de tours |
| `app/Http/Controllers/Api/Travel/TourCategoryController.php` | CRUD de categorías |
| `app/Services/TourAvailabilityService.php` | Cálculo de capacidad disponible por fecha |
| `app/Http/Controllers/Api/Travel/ReviewController.php` | Testimonios |
| `app/Models/Tour.php` | Modelo de tour |
| `app/Models/TourCategory.php` | Modelo de categoría |
| `app/Models/TourAvailability.php` | Modelo de disponibilidad |
| `app/Models/Review.php` | Modelo de reseña |
| `app/Http/Requests/TourRequest.php` | Validación de tour |
| `app/Http/Resources/TourResource.php` | Serialización de tour |
