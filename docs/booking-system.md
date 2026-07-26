# Sistema de Agendamiento Unificado

## Índice

1. [Visión general](#visión-general)
2. [Schema de base de datos](#schema-de-base-de-datos)
3. [Tipos de agendamiento](#tipos-de-agendamiento)
4. [API Reference](#api-reference)
5. [Flujo de estados](#flujo-de-estados)
6. [Pagos e invoices](#pagos-e-invoices)
7. [Arquitectura interna](#arquitectura-interna)
8. [Agregar un nuevo tipo de agendamiento](#agregar-un-nuevo-tipo-de-agendamiento)

---

## Visión general

El sistema utiliza una tabla `bookings` unificada que soporta cualquier tipo de agendamiento mediante una relación polimórfica (`bookable_type` / `bookable_id`). El discriminador legible `booking_type` (`tour` \| `transport`) **se deriva** de `bookable_type` mediante un accessor del modelo y **no se persiste** como columna, evitando que ambos campos puedan desincronizarse. Sigue disponible en la API (entrada y salida) como hasta ahora.

Los campos específicos de cada tipo se guardan en tablas de extensión 1:1 (p.ej. `transport_booking_details`). La tabla base nunca cambia cuando se agrega un nuevo tipo.

```
bookings (base)
    ├── Tour booking      → bookable: Tour,             sin tabla de extensión
    ├── Transport booking → bookable: TransportVehicle, + transport_booking_details
    └── [futuro]          → bookable: Accommodation,    + accommodation_booking_details
```

---

## Schema de base de datos

### `bookings`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | bigint PK | |
| `user_id` | uuid FK → users | Cliente que reserva |
| `bookable_type` | varchar(100) | FQCN del modelo: `App\Models\Tour`, `App\Models\TransportVehicle` |
| `bookable_id` | bigint unsigned | ID del recurso reservado |
| `starts_at` | datetime | Inicio del servicio (fecha a medianoche para tours) |
| `ends_at` | datetime nullable | Fin del servicio (null para tours de un día) |
| `party_size` | uint | Personas (tours) o cantidad de vehículos (transport) |
| `total_price` | decimal(12,2) | Precio final calculado al momento de la reserva (ya con extras, opción de vehículo y descuento de cupón) |
| `service_fees` | json nullable | Snapshot de los add-ons cobrados (tours). Ver [tours.md](tours.md) |
| `upgrade_label` | varchar nullable | Nombre de la opción de vehículo elegida (tours). Configurada por tour |
| `upgrade_surcharge` | decimal(12,2) nullable | Cargo de la opción de vehículo (snapshot) |
| `coupon_id` | bigint FK → coupons nullable | Cupón aplicado (`nullOnDelete`). Ver [coupons.md](coupons.md) |
| `discount_amount` | decimal(12,2) nullable | Descuento aplicado por el cupón (snapshot) |
| `pickup_address` | varchar(500) nullable | Dirección de recogida indicada por el cliente (tours) |
| `pickup_lat` / `pickup_lng` | decimal(10,7) nullable | Marcador de recogida en el mapa (tours) |
| `currency_code` | char(3) | ISO 4217. Default: `USD` |
| `status` | varchar(20) | `pending` \| `confirmed` \| `cancelled` |
| `notes` | text nullable | Notas libres del cliente |
| `created_at` / `updated_at` | timestamp | |

> **Columnas en desuso:** `upgrade_vehicle_id` existe pero ya no se usa (la opción de vehículo pasó de basarse en el módulo de transporte a configurarse por tour). Se conserva para evitar una migración destructiva; puede eliminarse en una limpieza futura.

> `booking_type` no es columna: se deriva de `bookable_type` (ver accessor `getBookingTypeAttribute` en el modelo `Booking`).

**Índices:**
- `user_id`
- `status`
- `starts_at`
- `(bookable_type, bookable_id, starts_at, ends_at)` (`bookings_bookable_schedule_idx`) — consultas de solapamiento en transport; cubre también `(bookable_type, bookable_id)` como prefijo para el eager-load del polimórfico

### `transport_booking_details`

Extensión 1:1 para campos exclusivos del agendamiento de transporte.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | bigint PK | |
| `booking_id` | bigint unique FK → bookings | |
| `pickup_location` | varchar(255) | Lugar de recogida |
| `dropoff_location` | varchar(255) | Lugar de destino |
| `rental_type` | varchar(10) | `hourly` \| `daily` |

### `invoices`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | bigint PK | |
| `booking_id` | bigint unique FK → bookings (`restrictOnDelete`) | Un invoice por booking. No se permite borrar un booking con factura (documento fiscal). |
| `amount` | decimal(12,2) | |
| `currency_code` | char(3) | Moneda al momento de emisión (snapshot del booking). Default: `USD` |
| `status` | varchar | `pending` \| `issued` |
| `issued_at` | timestamp nullable | |
| `deleted_at` | timestamp nullable | Soft delete — las facturas no se eliminan físicamente |

> Los campos del DTE (`dte_type`, `dte_number`, `dte_generation_code`, `dte_seal`, `dte_status`, etc.) se documentan en [dte-facturacion-electronica.md](dte-facturacion-electronica.md). La antigua columna `dte_code` fue eliminada por redundante; el "sello" del MH vive en `dte_seal` y la API sigue exponiendo `dte_code` como alias por compatibilidad.

### `payments`

Tabla polimórfica, todos los pagos apuntan a `App\Models\Booking`.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `payable_type` | varchar | `App\Models\Booking` |
| `payable_id` | bigint | `bookings.id` |
| `gateway` | varchar | `paypal` \| `stripe` \| `wompi` \| `manual` |
| `method` | varchar nullable | `card`, `bank_transfer`, etc. |
| `amount` | decimal(12,2) | |
| `currency_code` | char(3) | |
| `status` | varchar | `pending` \| `paid` \| `failed` |
| `transaction_reference` | varchar nullable | ID externo del gateway |
| `payload` | json nullable | Respuesta raw del gateway |
| `paid_at` | timestamp nullable | |

---

## Tipos de agendamiento

### Tour booking (`booking_type: "tour"`)

Reserva de una experiencia de tour para una fecha y número de personas.

**Campos requeridos al crear:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `tour_id` | integer | ID del tour |
| `booking_date` | `Y-m-d` | Fecha del tour (debe ser hoy o futuro) |
| `pax_count` | integer ≥ 1 | Número de personas |

**Campos opcionales:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `service_fees` | array&lt;int&gt; | Índices de `tour.service_fees` seleccionados (add-ons) |
| `upgrade_option_index` | integer ≥ 0 | Índice de la opción de vehículo elegida en `tour.vehicle_options` (omitir = "Sin vehículo") |
| `coupon_code` | string | Código de cupón a aplicar. Ver [coupons.md](coupons.md) |
| `pickup_address` | string(500) | Dirección de recogida |
| `pickup_lat` / `pickup_lng` | numeric | Marcador de recogida (ambos o ninguno) |
| `notes` | string | Notas del cliente |

**Cálculo de precio (pipeline):**

1. **Precio por persona base:** `tour.price` (ya no existe precio de oferta; los descuentos vienen solo del cupón).
2. **Tarifa de grupo escalonada:** se aplica el descuento `%` del tramo de `tour.pricing_tiers` con mayor `min_pax ≤ pax_count`.
3. **Subtotal:** `precio_por_persona × pax_count`.
4. **+ Servicios extra** (`service_fees`): cada add-on suma `amount` (fijo) o `amount × pax` (por persona).
5. **+ Opción de vehículo** (`upgrade_option_index`): suma el `surcharge` de la opción configurada en el tour.
6. **− Cupón** (`coupon_code`): descuento sobre el total anterior (ver [coupons.md](coupons.md)).
7. **= `total_price`**.

> El precio base del tour **nunca se altera**; tramos, extras, opción de vehículo y cupón se resuelven en el servidor a partir de la configuración del tour y del cupón (los montos que envíe el cliente son solo para previsualización).

**Validaciones de negocio:**
- La capacidad disponible en la fecha debe ser ≥ `pax_count`
- La **antelación mínima GLOBAL** (`app.booking_min_advance_days`, ver [settings.md](settings.md)) debe respetarse
- Si el setting `app.max_daily_bookings` está configurado, no puede superarse ese límite diario
- `upgrade_option_index` debe existir entre las opciones del tour
- El cupón debe ser válido (vigencia, ámbito, mínimos, límites de uso)

> **Moneda y política globales:** la `currency_code` de la reserva se toma de la
> **moneda global del sitio** (no del tour/vehículo). La **cancelación** por parte
> del cliente se bloquea dentro de la ventana global `app.booking_cancellation_hours`
> previa al inicio (el admin puede cancelar siempre). El **cupón aplicado** queda
> registrado en la reserva (`coupon_id`, `coupon_code`, `discount_amount`) y se
> muestra en el panel para su seguimiento.

**Mapeo a la tabla `bookings`:**

| Campo del request | Columna en `bookings` |
|-------------------|-----------------------|
| `booking_date` | `starts_at` (a medianoche) |
| — | `ends_at = null` |
| `pax_count` | `party_size` |

---

### Transport booking (`booking_type: "transport"`)

Alquiler de un vehículo por horas o días, con puntos de recogida y destino.

**Campos requeridos al crear:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `transport_vehicle_id` | integer | ID del vehículo |
| `pickup_at` | `Y-m-d H:i:s` | Fecha y hora de recogida |
| `dropoff_at` | `Y-m-d H:i:s` | Fecha y hora de entrega (debe ser después de pickup) |
| `pickup_location` | string | Dirección o nombre del punto de recogida |
| `dropoff_location` | string | Dirección o nombre del destino |
| `rental_type` | `hourly` \| `daily` | Modalidad de alquiler |
| `quantity` | integer ≥ 1 | Número de vehículos (opcional, default 1) |
| `currency_code` | char(3) | Opcional, hereda del vehículo si no se envía |

**Cálculo de precio:**
- `hourly`: `vehicle.hourly_rate × horas × quantity` (mínimo 1 hora)
- `daily`: `vehicle.daily_rate × días × quantity` (mínimo 1 día, días = ceil(horas / 24))

**Validaciones de negocio:**
- No debe existir otro booking del mismo vehículo con solapamiento de fechas en estado `pending` o `confirmed`

**Mapeo a la tabla `bookings`:**

| Campo del request | Columna |
|-------------------|---------|
| `pickup_at` | `bookings.starts_at` |
| `dropoff_at` | `bookings.ends_at` |
| `quantity` | `bookings.party_size` |
| `pickup_location` | `transport_booking_details.pickup_location` |
| `dropoff_location` | `transport_booking_details.dropoff_location` |
| `rental_type` | `transport_booking_details.rental_type` |

---

## API Reference

### Crear un booking

```
POST /api/bookings
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

**Body — Tour:**
```json
{
  "data": {
    "type": "bookings",
    "attributes": {
      "booking_type": "tour",
      "tour_id": 1,
      "booking_date": "2026-06-15",
      "pax_count": 3,
      "service_fees": [0, 2],
      "upgrade_option_index": 1,
      "coupon_code": "BIENVENIDO10",
      "pickup_address": "Hotel Real, Col. Escalón",
      "pickup_lat": 13.6989,
      "pickup_lng": -89.1914,
      "notes": "Solicito guía en inglés"
    }
  }
}
```

**Body — Transport:**
```json
{
  "data": {
    "type": "bookings",
    "attributes": {
      "booking_type": "transport",
      "transport_vehicle_id": 2,
      "pickup_at": "2026-06-15 08:00:00",
      "dropoff_at": "2026-06-15 18:00:00",
      "pickup_location": "San Salvador Centro",
      "dropoff_location": "Aeropuerto El Salvador",
      "rental_type": "daily",
      "quantity": 1
    }
  }
}
```

**Response 200:**
```json
{
  "data": {
    "type": "bookings",
    "id": "7",
    "attributes": {
      "booking_type": "tour",
      "user_id": "...",
      "tour_id": 1,
      "tour_title": "Volcan Santa Ana Sunrise",
      "booking_date": "2026-06-15",
      "pax_count": 3,
      "starts_at": "2026-06-15T00:00:00.000000Z",
      "ends_at": null,
      "party_size": 3,
      "total_price": "195.00",
      "currency_code": "USD",
      "status": "pending",
      "notes": "Solicito guía en inglés",
      "created_at": "2026-05-30T..."
    }
  }
}
```

---

### Listar bookings

```
GET /api/bookings
Authorization: Bearer {token}
```

**Query params:**

| Param | Descripción |
|-------|-------------|
| `booking_type` | Filtrar por tipo: `tour` \| `transport` |
| `status` | Filtrar por estado: `pending` \| `confirmed` \| `cancelled` |
| `date_from` | Fecha inicio (`Y-m-d`) — filtra sobre `starts_at` |
| `date_to` | Fecha fin (`Y-m-d`) — filtra sobre `starts_at` |
| `page[size]` | Tamaño de página (JSON:API pagination) |
| `page[number]` | Número de página |

Los admins ven todos los bookings; los clientes ven solo los suyos.

---

### Ver un booking

```
GET /api/bookings/{id}
Authorization: Bearer {token}
```

---

### Cambiar estado

```
PATCH /api/bookings/{id}
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "bookings",
    "attributes": {
      "status": "confirmed"
    }
  }
}
```

Al confirmar:
- Se crea el `Invoice` automáticamente (vía `BookingObserver`)
- Se envía `ReservationConfirmedNotification` al cliente
- Se envía `InvoiceCreatedNotification` si hay invoice
- Se envía `AdminAlertNotification` a los emails configurados en `services.notifications.admin_emails`

---

## Flujo de estados

```
[creación] → pending
                │
                ├─── confirmed ──→ [invoice creado, notificaciones enviadas]
                │
                └─── cancelled
```

Los estados válidos son `pending`, `confirmed` y `cancelled`. No hay restricción de transiciones en el código — el estado puede actualizarse libremente mediante `PATCH /api/bookings/{id}`.

---

## Pagos e invoices

### Crear un pago

```
POST /api/payments
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "payments",
    "attributes": {
      "payable_type": "booking",
      "payable_id": 7,
      "gateway": "stripe",
      "amount": 195.00,
      "currency_code": "USD"
    }
  }
}
```

### Iniciar checkout (gateways online)

```
POST /api/payments/checkout
```

```json
{
  "data": {
    "attributes": {
      "gateway": "paypal",
      "payable_type": "booking",
      "payable_id": 7,
      "amount": 195.00,
      "currency_code": "USD"
    }
  }
}
```

Gateways soportados: `paypal`, `stripe`, `wompi`.

### Verificar pago tras redirect

```
POST /api/payments/verify
```

```json
{
  "data": {
    "attributes": {
      "gateway": "paypal",
      "payment_id": 3,
      "token": "ORDER_ID_DEL_GATEWAY"
    }
  }
}
```

---

## Arquitectura interna

### Handler Registry Pattern

El `BookingService` no contiene lógica específica de ningún tipo. En su lugar, delega a un handler registrado por `booking_type`.

```
BookingController
    └── BookingService::create(user, bookingType, data)
            └── handlers[$bookingType]
                    ├── validate(data)   ← reglas de negocio + locking pesimista
                    └── prepare(data)    ← calcula precio, construye atributos
```

**Flujo completo de `create()`:**

```php
// Dentro de DB::transaction():
$handler->validate($data);          // lanza ValidationException si falla
$prepared = $handler->prepare($data); // retorna atributos + 'details' opcional
$details = Arr::pull($prepared, 'details');

// $prepared ya incluye bookable_type/bookable_id (del handler); booking_type
// se deriva de bookable_type, por eso NO se asigna aquí.
$booking = Booking::create([
    ...$prepared,
    'user_id' => $user->id,
    'status'  => 'pending',
]);

if ($details) {
    $booking->transportDetail()->create($details); // tabla de extensión
}
```

### Registro de handlers

Los handlers se registran en `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(BookingService::class, function ($app) {
    $service = new BookingService();
    $service->registerHandler('tour',      $app->make(TourBookingHandler::class));
    $service->registerHandler('transport', $app->make(TransportBookingHandler::class));
    return $service;
});
```

### Observer

`BookingObserver` escucha los eventos `created` y `updated` del modelo `Booking`:

- `created`: crea invoice si el status inicial es `confirmed`
- `updated`: crea invoice y envía notificaciones si el status cambió a `confirmed`

La lógica de notificación vive en `BookingService::notifyConfirmation()`, que detecta el tipo y personaliza el mensaje.

---

## Agregar un nuevo tipo de agendamiento

Ejemplo: `accommodation`.

### 1. Tabla de extensión (si aplica)

```bash
php artisan make:migration create_accommodation_booking_details_table
```

```php
Schema::create('accommodation_booking_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->cascadeOnDelete()->unique();
    $table->string('room_type', 50);
    $table->time('check_in_time')->nullable();
    $table->time('check_out_time')->nullable();
    $table->timestamps();
});
```

### 2. Modelo de extensión

```php
// app/Models/AccommodationBookingDetail.php
class AccommodationBookingDetail extends Model
{
    protected $fillable = ['booking_id', 'room_type', 'check_in_time', 'check_out_time'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
```

### 3. Constante y mapeo en Booking

Agrega la constante y regístrala en `BOOKABLE_MAP`. Ese mapa es la única fuente de verdad: de él se derivan tanto el accessor `booking_type` como el helper `bookableClassFor()` usado en los filtros.

```php
// app/Models/Booking.php
public const TYPE_ACCOMMODATION = 'accommodation';

public const BOOKABLE_MAP = [
    self::TYPE_TOUR          => Tour::class,
    self::TYPE_TRANSPORT     => TransportVehicle::class,
    self::TYPE_ACCOMMODATION => Accommodation::class, // ← agregar
];
```

### 4. Handler

```php
// app/Services/Booking/AccommodationBookingHandler.php
class AccommodationBookingHandler implements BookingHandlerInterface
{
    public function validate(array $data): void
    {
        // validar disponibilidad, solapamiento de fechas, etc.
    }

    public function prepare(array $data): array
    {
        return [
            'bookable_type' => Accommodation::class,
            'bookable_id'   => $data['accommodation_id'],
            'starts_at'     => Carbon::parse($data['check_in']),
            'ends_at'       => Carbon::parse($data['check_out']),
            'party_size'    => $data['guests'],
            'total_price'   => /* cálculo */,
            'currency_code' => 'USD',
            'details' => [
                'room_type'       => $data['room_type'],
                'check_in_time'   => $data['check_in_time'] ?? null,
                'check_out_time'  => $data['check_out_time'] ?? null,
            ],
        ];
    }
}
```

### 5. Registrar el handler

```php
// app/Providers/AppServiceProvider.php
$service->registerHandler('accommodation', $app->make(AccommodationBookingHandler::class));
```

### 6. Agregar validación al request

```php
// app/Http/Requests/BookingRequest.php
$rules['data.attributes.booking_type'] = ['required', 'string', Rule::in([
    Booking::TYPE_TOUR,
    Booking::TYPE_TRANSPORT,
    Booking::TYPE_ACCOMMODATION, // ← agregar
])];

// Reglas condicionales del nuevo tipo:
$rules['data.attributes.accommodation_id'] = ['required_if:...accommodation', 'integer', 'exists:accommodations,id'];
// ...
```

### 7. Exponer en el resource

```php
// app/Http/Resources/BookingResource.php
$isAccommodation = $booking->booking_type === Booking::TYPE_ACCOMMODATION;
// ...
'accommodation_id' => $isAccommodation ? $booking->bookable_id : null,
'room_type'        => $isAccommodation ? $detail?->room_type : null,
```

Con estos 7 pasos, el nuevo tipo queda integrado sin modificar tablas base ni rutas.
