# Módulo de Transporte

Gestión del catálogo de vehículos de transporte disponibles para alquiler por horas o por días. Incluye verificación de disponibilidad y galería de imágenes.

## Índice

1. [Modelo](#modelo)
2. [API Reference](#api-reference)
3. [Verificación de disponibilidad](#verificación-de-disponibilidad)
4. [Gestión de imágenes](#gestión-de-imágenes)

---

## Modelo

### `TransportVehicle`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `title` | string | Nombre/modelo del vehículo |
| `vehicle_type` | string | Tipo: `van`, `bus`, `car`, `truck`, etc. |
| `description` | text | Descripción del vehículo |
| `location` | string | Base o ubicación del vehículo |
| `hourly_rate` | decimal(12,2) | Tarifa por hora |
| `daily_rate` | decimal(12,2) | Tarifa por día |
| `capacity` | integer | Capacidad de pasajeros |
| `currency_code` | char(3) | Moneda de las tarifas (ISO 4217). Validado contra `currencies.code`. |
| `features` | json array | Lista de características o equipamiento |
| `is_active` | boolean | Visible en el catálogo público |

**Relaciones:**
- `morphMany(Booking, 'bookable')` — reservas del vehículo
- Media collections vía Spatie: `featured_image` (1 imagen), `gallery` (múltiples)

---

## API Reference

### Rutas públicas (sin autenticación)

```
GET /api/transport-vehicles          → Listar vehículos activos
GET /api/transport-vehicles/{id}     → Ver detalle de un vehículo
GET /api/transport-vehicles/{id}/availability → Ver disponibilidad
```

### Rutas de administración (requieren rol admin)

```
POST   /api/transport-vehicles                      → Crear vehículo
PATCH  /api/transport-vehicles/{id}                 → Actualizar vehículo
POST   /api/transport-vehicles/{id}/featured-image  → Subir imagen destacada
POST   /api/transport-vehicles/{id}/gallery          → Subir galería
DELETE /api/transport-vehicles/{id}/gallery/{media} → Eliminar imagen
```

---

### Listar vehículos

```
GET /api/transport-vehicles
```

El listado público soporta paginación, orden y filtros estilo JSON:API.

**Filtros (`filter[campo]=valor`):**

| Param | Descripción |
|-------|-------------|
| `filter[vehicleType]` | Filtrar por tipo de vehículo (`van`, `bus`, `car`, etc.) |
| `filter[capacityMin]` | Capacidad mínima de pasajeros |
| `filter[rateMin]` | Tarifa mínima |
| `filter[rateMax]` | Tarifa máxima |
| `filter[location]` | Filtrar por ubicación |
| `filter[search]` | Búsqueda libre en título y ubicación |

**Orden (`sort`):** lista separada por coma; prefijo `-` para descendente. Campos permitidos: `daily_rate`, `hourly_rate`, `capacity`, `created_at`. Por defecto (sin `sort`) se ordena por los más recientes. Un campo no permitido devuelve **HTTP 400**.

**Paginación (JSON:API):**

| Param | Descripción |
|-------|-------------|
| `page[size]` | Tamaño de página (default 15) |
| `page[number]` | Número de página |

La respuesta incluye un bloque `meta` con `current_page`, `last_page`, `per_page` y `total`.

**Ejemplos:**

```
GET /api/v1/transport-vehicles?filter[capacityMin]=10&sort=daily_rate&page[size]=12
GET /api/v1/transport-vehicles?filter[vehicleType]=van&filter[rateMax]=250&sort=-created_at
GET /api/v1/transport-vehicles?filter[search]=hiace&sort=capacity,daily_rate&page[number]=2
```

**Response:**
```json
{
  "data": [{
    "type": "transport-vehicles",
    "id": "3",
    "attributes": {
      "title": "Van Toyota HiAce",
      "vehicle_type": "van",
      "description": "Van de 15 pasajeros...",
      "location": "San Salvador",
      "hourly_rate": "35.00",
      "daily_rate": "200.00",
      "capacity": 15,
      "currency_code": "USD",
      "features": ["Aire acondicionado", "GPS", "WiFi"],
      "is_active": true,
      "featured_image": "https://..."
    }
  }],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 40
  }
}
```

---

### Crear / actualizar vehículo

```
POST /api/transport-vehicles
PATCH /api/transport-vehicles/{id}
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "transport-vehicles",
    "attributes": {
      "title": "Van Toyota HiAce",
      "vehicle_type": "van",
      "description": "Descripción del vehículo",
      "location": "San Salvador",
      "hourly_rate": 35.00,
      "daily_rate": 200.00,
      "capacity": 15,
      "currency_code": "USD",
      "features": ["Aire acondicionado", "GPS"],
      "is_active": true
    }
  }
}
```

---

## Verificación de disponibilidad

Permite al cliente consultar si un vehículo está disponible en un rango de fechas antes de crear la reserva.

```
GET /api/transport-vehicles/{id}/availability?pickup_at=2026-07-01 08:00:00&dropoff_at=2026-07-01 18:00:00
```

**Response:**
```json
{
  "data": {
    "type": "availability",
    "attributes": {
      "available": true,
      "conflicting_bookings": []
    }
  }
}
```

Si hay conflicto:
```json
{
  "data": {
    "type": "availability",
    "attributes": {
      "available": false,
      "conflicting_bookings": [
        {
          "starts_at": "2026-07-01T06:00:00Z",
          "ends_at": "2026-07-01T14:00:00Z"
        }
      ]
    }
  }
}
```

La lógica de solapamiento verifica que no existan reservas en estado `pending` o `confirmed` con fechas que se intersecten con el rango solicitado. Ver [booking-system.md](booking-system.md) para más detalle.

---

## Gestión de imágenes

**Imagen destacada (reemplaza la anterior):**
```
POST /api/transport-vehicles/{id}/featured-image
Content-Type: multipart/form-data
Authorization: Bearer {token}

image: [archivo]
```

**Galería:**
```
POST /api/transport-vehicles/{id}/gallery
Content-Type: multipart/form-data
Authorization: Bearer {token}

images[]: [archivo1]
images[]: [archivo2]
```

**Eliminar imagen:**
```
DELETE /api/transport-vehicles/{id}/gallery/{media_id}
Authorization: Bearer {token}
```

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/TransportVehicleController.php` | CRUD y disponibilidad |
| `app/Models/TransportVehicle.php` | Modelo del vehículo |
| `app/Models/TransportBookingDetail.php` | Detalles específicos de reserva de transporte |
| `app/Http/Requests/TransportVehicleRequest.php` | Validación |
| `app/Http/Resources/TransportVehicleResource.php` | Serialización |
