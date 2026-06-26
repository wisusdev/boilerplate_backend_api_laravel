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
| `price` | decimal(12,2) | Precio por persona |
| `max_capacity` | integer | Capacidad máxima por fecha |
| `location` | string | Ubicación o destino |
| `category_id` | bigint FK → tour_categories nullable | Categoría normalizada (`nullOnDelete`). Reemplaza al antiguo campo de texto `category`. |
| `currency_code` | char(3) | Moneda del precio (ISO 4217). Validado contra `currencies.code`. |
| `itinerary` | json array | Lista de pasos del itinerario |
| `highlights` | json array | Puntos destacados del tour |
| `map_url` | string nullable | URL de mapa embebido (Google Maps) |
| `map_markers` | json array | Coordenadas para el mapa interactivo |
| `faqs` | json array | Preguntas frecuentes |
| `is_active` | boolean | Visible en el catálogo público |

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
GET /api/tours          → Listar tours activos
GET /api/tours/{id}     → Ver detalle de un tour
```

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

**Query params:**

| Param | Descripción |
|-------|-------------|
| `filter[category_id]` | Filtrar por categoría (ID de `tour_categories`) |
| `filter[location]` | Filtrar por ubicación |
| `sort` | `price`, `title`, `-created_at` |
| `page[number]` | Número de página |
| `page[size]` | Tamaño de página (default 15) |

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
  }]
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
      "currency_code": "USD",
      "category_id": 1,
      "max_capacity": 20,
      "location": "Santa Ana",
      "is_active": true,
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
