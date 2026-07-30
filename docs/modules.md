# Módulos de negocio — Travel API

Documentación de los módulos de turismo (tours, transporte, reservas, reseñas y
suscriptores) y de las capacidades añadidas sobre el boilerplate. Todos los
endpoints siguen el formato **JSON:API** (`Accept: application/vnd.api+json`) y
cuelgan del prefijo `/api/v1`.

> Convención de escritura: los endpoints de creación/edición reciben el documento
> `{ "data": { "type": "...", "attributes": { ... } } }`. Los de sólo-lectura
> aceptan `filter[...]`, `sort` y paginación `page[number]` / `page[size]`.

---

## 1. Tours

Modelo `Tour` (tabla `tours`). Campos relevantes:

| Campo | Tipo | Notas |
|---|---|---|
| `price` | decimal | Precio base por persona |
| `sale_price` | decimal, nullable | **Precio de oferta**. Si es menor que `price`, es el que se cobra |
| `child_price` | decimal, nullable | Precio infantil (informativo en la reserva) |
| `duration_days` / `duration_nights` | int, nullable | Duración |
| `min_advance_days` | int, nullable | Antelación mínima para reservar (validada en el booking) |
| `cancellation_hours` | int, nullable | Horas antes del inicio para cancelar sin cargo (informativo) |
| `itinerary`, `highlights`, `includes`, `excludes`, `faqs` | json | Bloques de contenido |
| `service_fees` | json, nullable | Add-ons opcionales: `[{ name, amount, calc }]`, `calc = fixed \| per_person` |
| `is_featured` | bool | Destacado en la portada |
| `meta_title`, `meta_description` | string, nullable | SEO por producto |
| `map_url`, `map_markers` | | Mapa |

**Precio efectivo**: al reservar, `TourBookingHandler` cobra `sale_price` cuando
existe y es menor que `price`, multiplicado por el número de personas, y suma los
`service_fees` seleccionados (snapshot guardado en `bookings.service_fees`).

**Endpoints**

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/tours` | Público — `filter[categoryId,priceMin,priceMax,location,search,isFeatured]`, `sort[price,title,max_capacity,created_at]`. Expone `average_rating` y `reviews_count`. |
| GET | `/tours/{tour}` | Público (por slug o id) |
| POST | `/tours` | Admin |
| PATCH | `/tours/{tour}` | Admin (parcial: p.ej. sólo `is_featured` / `is_active`) |
| POST | `/tours/{tour}/featured-image`, `/tours/{tour}/gallery` | Admin |
| DELETE | `/tours/{tour}/gallery/{media}` | Admin |

---

## 2. Transporte

Modelo `TransportVehicle` (tabla `transport_vehicles`). Añadidos: `sale_hourly_rate`,
`sale_daily_rate` (tarifas de oferta, cobradas igual que en tours), `meta_title`,
`meta_description`. Expone `average_rating` / `reviews_count`.

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/transport-vehicles` | Público — `filter[vehicleType,capacityMin,rateMin,rateMax,location,search]` |
| GET | `/transport-vehicles/types` | Público — tipos de vehículo distintos (para poblar filtros) |
| GET | `/transport-vehicles/{id}` | Público |
| GET | `/transport-vehicles/{id}/availability` | Público — `pickup_at`, `dropoff_at` |
| POST / PATCH / uploads | `/transport-vehicles*` | Admin |

---

## 3. Reservas y tarifas de servicio

`bookings.service_fees` guarda el snapshot de los add-ons cobrados
(`[{ name, amount, calc, total }]`). En la reserva de tour se envían por índice:

```
POST /api/v1/bookings
{ "data": { "type": "bookings", "attributes": {
  "booking_type": "tour", "tour_id": 1, "booking_date": "2026-08-01",
  "pax_count": 2, "service_fees": [0, 1]
}}}
```

Reglas validadas: cupo disponible, `max_daily_bookings` (ajuste) y
`min_advance_days` del tour.

---

## 4. Reseñas de producto (`product_reviews`)

Reseñas de usuarios con **compra verificada**, separadas de los testimonios de
marketing (`reviews`). Polimórficas a `Tour` / `TransportVehicle`.

- **Compra verificada**: sólo puede reseñar quien tenga una reserva no cancelada
  del producto. Una reseña por usuario y producto (índice único).
- **Moderación**: `is_approved` (visible/oculta) y `admin_reply` (respuesta del equipo).
- Los tours y vehículos exponen `average_rating` y `reviews_count` (sólo aprobadas).

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/product-reviews` | Público — sólo aprobadas. `filter[reviewableType=tour\|transport, reviewableId]` |
| GET | `/product-reviews/admin` | Admin — todas. `filter[isApproved, reviewableType]` |
| GET | `/product-reviews/eligibility?reviewable_type=&reviewable_id=` | Auth — `{ can_review, has_booking, has_reviewed }` |
| POST | `/product-reviews` | Auth (compra verificada) |
| PATCH | `/product-reviews/{id}` | Autor (rating/comentario) o admin (moderación/respuesta) |
| DELETE | `/product-reviews/{id}` | Autor o admin |

---

## 5. Suscriptores a ofertas (leads)

Modelo `Subscriber` (tabla `subscribers`): `email` (único), `name`, `source`,
`locale`, `status` (`subscribed` \| `unsubscribed`), `unsubscribed_at`.

- **Alta pública idempotente**: si el correo ya existe y estaba de baja, se
  reactiva; si ya estaba suscrito responde `200`. Correos **sólo** en alta nueva o
  reactivación.
- **Correos** (vía `Notification`, tolerantes a fallo — no rompen el alta):
  - `SubscriberWelcomeNotification` al suscriptor (remitente de marca).
  - `AdminAlertNotification` a `ADMIN_NOTIFICATION_EMAILS`.
- **Configurable**: se muestra/rechaza según el ajuste
  `app.offers_subscription_enabled` (ver §6). Si está desactivado, `POST` responde `403`.

| Método | Ruta | Acceso |
|---|---|---|
| POST | `/subscribers` | Público (`throttle:forms`) |
| POST | `/subscribers/unsubscribe` | Público — `{ email }` |
| GET | `/subscribers` | Admin — `filter[status, search]` |
| PATCH | `/subscribers/{id}` | Admin — `attributes.status` |
| DELETE | `/subscribers/{id}` | Admin |

---

## 6. Ajustes (Settings)

- Nuevo flag en la fila `app`: **`offers_subscription_enabled`** (bool, default `true`).
  Controla la visibilidad del módulo de suscripción (footer + popup) y el rechazo
  de altas en backend.
- `GET /settings` (público) devuelve el subconjunto seguro (`app`,
  `social_auth_services`) sin credenciales. Corregido un `TypeError` cuando el
  visitante no está autenticado (`$isAdmin` nulo).

---

## Tests

Cobertura añadida (`php artisan test`):

- `Feature/Travel/SubscriberTest` — alta idempotente, baja, correo, toggle `403`, CRUD admin.
- `Feature/Travel/ProductReviewTest` — compra verificada, unicidad, visibilidad pública, moderación, agregados.
- `Feature/Travel/SettingsPublicTest` — respuesta sin auth y sin fuga de credenciales.
- `Unit/Services/Booking/TourBookingHandlerTest` — oferta cobrada, `service_fees`, `min_advance_days`.
