# Cupones de descuento

Sistema de cupones aplicables a las reservas (tours y transporte). El descuento se resuelve en el servidor al crear la reserva y se propaga a la factura mediante `bookings.total_price`.

> **Único mecanismo de descuento:** ya no existe "precio de oferta" en tours ni
> vehículos. La única forma de obtener un descuento es que el cliente aplique un
> **cupón** al reservar. El cupón usado queda registrado en la reserva
> (`coupon_id`, `coupon_code`, `discount_amount`) y se muestra en el panel de
> administración para su seguimiento.

## Índice

1. [Modelo `Coupon`](#modelo-coupon)
2. [Cálculo del descuento](#cálculo-del-descuento)
3. [Validación](#validación)
4. [API — administración](#api--administración)
5. [API — validar cupón (cliente)](#api--validar-cupón-cliente)
6. [Aplicación en la reserva](#aplicación-en-la-reserva)

---

## Modelo `Coupon`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | |
| `code` | varchar(60) unique | Código; se normaliza a MAYÚSCULAS sin espacios |
| `description` | varchar nullable | Descripción interna |
| `type` | varchar(20) | `percentage` \| `fixed` |
| `value` | decimal(12,2) | Porcentaje (0–100) o monto fijo |
| `max_discount` | decimal(12,2) nullable | Tope de descuento (solo `percentage`) |
| `min_pax` | uint nullable | Mínimo de pasajeros para aplicar |
| `min_amount` | decimal(12,2) nullable | Monto mínimo de la reserva |
| `applies_to` | varchar(20) | `all` \| `tour` \| `transport` |
| `usage_limit` | uint nullable | Límite global de usos (null = ilimitado) |
| `used_count` | uint | Usos consumidos (se incrementa al canjear) |
| `per_user_limit` | uint nullable | Límite de usos por usuario |
| `starts_at` / `expires_at` | timestamp nullable | Ventana de vigencia |
| `is_active` | boolean | Activo |

`bookings` guarda el cupón aplicado en `coupon_id` (`nullOnDelete`) y el `discount_amount` (snapshot).

---

## Cálculo del descuento

`Coupon::discountFor(float $subtotal)`:

- **`percentage`:** `subtotal × value / 100`, acotado por `max_discount` si existe.
- **`fixed`:** `value`.
- Nunca excede el subtotal.

El descuento se aplica sobre el **total completo** de la reserva (tarifa con tramos + servicios extra + opción de vehículo).

---

## Validación

`CouponService::validate($code, $context)` lanza `ValidationException` (campo `data.attributes.coupon_code`) si:

- El código no existe.
- El cupón no está vigente (inactivo, aún no inicia o expiró).
- `applies_to` no coincide con el tipo de reserva.
- `pax` &lt; `min_pax`.
- `subtotal` &lt; `min_amount`.
- `used_count` ≥ `usage_limit`.
- El usuario alcanzó su `per_user_limit` (reservas no canceladas con ese cupón).

Al **cancelar** una reserva con cupón se libera el uso (`used_count` decrementa).

---

## API — administración

Rutas protegidas por rol `admin|super-admin`.

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/coupons` | Listar (paginado; filtros `search`, `is_active`) |
| POST | `/api/v1/coupons` | Crear |
| PATCH | `/api/v1/coupons/{coupon}` | Actualizar |
| DELETE | `/api/v1/coupons/{coupon}` | Eliminar |

**Body (crear/actualizar):**
```json
{
  "data": {
    "type": "coupons",
    "attributes": {
      "code": "BIENVENIDO10",
      "description": "10% de bienvenida",
      "type": "percentage",
      "value": 10,
      "max_discount": 50,
      "applies_to": "all",
      "min_pax": null,
      "min_amount": null,
      "usage_limit": 100,
      "per_user_limit": 1,
      "starts_at": null,
      "expires_at": "2026-12-31T23:59:59",
      "is_active": true
    }
  }
}
```

> Un cupón `percentage` con `value > 100` es rechazado con error de validación.

---

## API — validar cupón (cliente)

Previsualiza validez y descuento sin aplicar nada. Requiere autenticación (para evaluar el límite por usuario).

```
POST /api/v1/coupons/validate
Authorization: Bearer {token}
```

**Body:**
```json
{
  "data": {
    "attributes": {
      "code": "BIENVENIDO10",
      "booking_type": "tour",
      "pax": 2,
      "amount": 200
    }
  }
}
```

**Respuesta (válido):**
```json
{
  "data": {
    "type": "coupon_validation",
    "attributes": { "valid": true, "code": "BIENVENIDO10", "type": "percentage", "value": "10.00", "discount": 20 }
  }
}
```

**Respuesta (no válido):** `valid: false` con `message` explicando el motivo (HTTP 200).

---

## Aplicación en la reserva

Al crear una reserva se envía `coupon_code` en los atributos (ver [booking-system.md](booking-system.md)). El descuento definitivo se recalcula en el servidor contra el total ya computado; el `amount` que envía el cliente al validar es solo para la previsualización.

## Archivos clave

- `app/Models/Coupon.php`
- `app/Services/CouponService.php`
- `app/Http/Controllers/Api/Travel/CouponController.php`
- `app/Http/Requests/CouponRequest.php`
- `app/Http/Resources/CouponResource.php`
