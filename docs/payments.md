# Módulo de Pagos

Gestión de pagos de reservas. Soporta cuatro gateways online (PayPal, Stripe, Wompi) y dos métodos manuales (transferencia bancaria, efectivo).

## Índice

1. [Modelo](#modelo)
2. [Flujo general de pago](#flujo-general-de-pago)
3. [API Reference](#api-reference)
4. [Gateways](#gateways)
5. [Monedas](#monedas)

---

## Modelo

### `Payment`

Tabla polimórfica que permite asociar pagos a cualquier modelo del sistema (actualmente sólo `Booking`).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `payable_type` | string | FQCN del modelo pagado (`App\Models\Booking`) |
| `payable_id` | bigint | ID del recurso pagado |
| `gateway` | string | `paypal` \| `stripe` \| `wompi` \| `manual` |
| `method` | string nullable | `card` \| `bank_transfer` \| `cash` |
| `amount` | decimal(12,2) | Monto del pago |
| `currency_code` | char(3) | Moneda (ISO 4217) |
| `status` | string | `pending` \| `paid` \| `failed` |
| `transaction_reference` | string nullable | ID del gateway externo |
| `payload` | json nullable | Respuesta raw del gateway |
| `paid_at` | timestamp nullable | Momento en que el pago fue confirmado |

---

## Flujo general de pago

```
1. Cliente crea reserva (Booking)
   → status: pending

2. Cliente inicia pago
   POST /api/payments/checkout
   → Se crea Payment en estado pending
   → Gateway retorna URL de redirección (PayPal) o client_secret (Stripe) o redirect (Wompi)

3. Cliente completa el pago en el gateway

4. Cliente retorna a la app y verifica el pago
   POST /api/payments/verify
   → Backend consulta al gateway el estado real
   → Si pagado: Payment.status = paid, Booking.status puede cambiar a confirmed

5. Admin confirma la reserva
   PATCH /api/bookings/{id} → { status: "confirmed" }
   → Se crea Invoice automáticamente
   → Se envían notificaciones
```

---

## API Reference

### Listar pagos

```
GET /api/payments
Authorization: Bearer {token}
```

Los clientes ven solo sus propios pagos. Los admins ven todos.

**Query params:**

| Param | Descripción |
|-------|-------------|
| `filter[status]` | `pending` \| `paid` \| `failed` |
| `filter[gateway]` | `paypal` \| `stripe` \| `wompi` \| `manual` |
| `filter[date_from]` | Fecha inicio (`Y-m-d`) |
| `filter[date_to]` | Fecha fin (`Y-m-d`) |
| `page[number]` | Número de página |

---

### Ver pago

```
GET /api/payments/{id}
Authorization: Bearer {token}
```

---

### Crear pago manual

Para registrar pagos por transferencia bancaria o efectivo (sin gateway online).

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
      "gateway": "manual",
      "method": "bank_transfer",
      "amount": 195.00,
      "currency_code": "USD"
    }
  }
}
```

---

### Iniciar checkout (gateways online)

```
POST /api/payments/checkout
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
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

**Response según gateway:**

**PayPal:**
```json
{
  "data": {
    "attributes": {
      "payment_id": 3,
      "redirect_url": "https://www.paypal.com/checkoutnow?token=ORDER_ID"
    }
  }
}
```

**Stripe:**
```json
{
  "data": {
    "attributes": {
      "payment_id": 3,
      "client_secret": "pi_xxx_secret_yyy",
      "publishable_key": "pk_live_..."
    }
  }
}
```

**Wompi:**
```json
{
  "data": {
    "attributes": {
      "payment_id": 3,
      "redirect_url": "https://checkout.wompi.sv/..."
    }
  }
}
```

---

### Verificar pago tras redirect

```
POST /api/payments/verify
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
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

El backend consulta al gateway el estado real del pago y actualiza `Payment.status` y `Payment.paid_at`.

---

## Webhooks (confirmación server-to-server)

`POST /api/payments/webhook/{gateway}` — `gateway`: `stripe` \| `paypal` \| `wompi`.

Endpoint **público** (los gateways no envían Bearer token) y **sin** middleware JSON:API. Es la **fuente de verdad** del estado del pago: si el cliente paga pero cierra el navegador antes de volver a la app (nunca se llama `/verify`), el gateway igualmente notifica por webhook y el pago se confirma.

**Flujo del handler** (`PaymentWebhookService`):

1. **Verifica la firma** del webhook; si es inválida responde `400` (el gateway reintenta).
2. Localiza el `Payment` local por `gateway` + `transaction_reference`.
3. Marca el pago `paid`/`failed` de forma **idempotente** (un pago ya `paid` no se reprocesa).

| Gateway | Verificación de firma | Cabecera | Referencia para emparejar |
|---------|-----------------------|----------|---------------------------|
| Stripe | HMAC-SHA256 (`t`/`v1`, tolerancia 300s) | `Stripe-Signature` | `payment_intent` (`pi_...`) |
| Wompi | HMAC-SHA256 sobre el cuerpo crudo | `X-Event-Signature` | `idTransaccion` |
| PayPal | API oficial `verify-webhook-signature` | cabeceras `PAYPAL-*` | `order_id` (`resource.supplementary_data.related_ids.order_id`) |

**Eventos que confirman el pago:** Stripe `payment_intent.succeeded`, Wompi estado `APROBADA`, PayPal `PAYMENT.CAPTURE.COMPLETED`. Los eventos de fallo (`payment_failed`, `RECHAZADA`, `PAYMENT.CAPTURE.DENIED`) marcan el pago `failed`.

**Configuración de secretos** (en Configuración → `payment_gateway`, cifrados; o variables de entorno):

| Clave en settings | Variable de entorno (fallback) |
|-------------------|--------------------------------|
| `stripe_webhook_secret` | `STRIPE_WEBHOOK_SECRET` |
| `wompi_webhook_secret` | `WOMPI_WEBHOOK_SECRET` |
| `paypal_webhook_id` | `PAYPAL_WEBHOOK_ID` |

> **Respuestas:** `200 {received:true,status:paid|failed|ignored|already_processed}` · `400` firma inválida · `404` gateway no soportado.

---

## Gateways

### PayPal

- **Flujo:** Server-side. Se crea una order en PayPal, el cliente es redireccionado a PayPal, y al volver se captura el pago via API.
- **Credenciales:** `client_id` y `client_secret` configurados en **Admin → Configuración → Pasarela de pagos**.
- **Servicio:** `app/Services/PaypalService.php`

### Stripe

- **Flujo:** Client-side. El backend crea un `PaymentIntent` y retorna el `client_secret`. El frontend usa Stripe.js para completar el pago sin redireccionamiento.
- **Credenciales:** `publishable_key` y `secret_key`.
- **Servicio:** `app/Services/StripeService.php`

### Wompi

- **Flujo:** Server-side con 3DS. El backend envía los datos de la tarjeta a Wompi y maneja el redirect 3DS si aplica.
- **Mercado:** El Salvador.
- **Credenciales:** `public_key` y `private_key`.
- **Servicio:** `app/Services/WompiService.php`

### Manual

- No requiere integración con gateway externo.
- Métodos: `bank_transfer`, `cash`.
- El admin registra el pago y confirma la reserva manualmente.

---

## Monedas

### `Currency`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `code` | char(3) unique | ISO 4217 (USD, EUR, etc.) |
| `name` | string | Nombre completo |
| `symbol` | string | Símbolo ($, €, etc.) |
| `rate_to_usd` | decimal(18,8) | Tasa de conversión respecto al USD |
| `is_default` | boolean | Moneda por defecto del sistema |
| `is_active` | boolean | Disponible para selección |

Solo puede haber **una moneda con `is_default = true`** al mismo tiempo. Esto lo **garantiza el modelo** `Currency`: al guardar una moneda con `is_default = true`, las demás se desmarcan automáticamente.

Los códigos de moneda que se envían al crear/editar tours, vehículos y consultas personalizadas se validan contra `currencies.code` (`exists:currencies,code`). En registros transaccionales (`bookings`, `invoices`, `payments`) el `currency_code` se conserva como *snapshot* histórico y no lleva FK.

```
GET /api/currencies        → Listar monedas activas (público)
GET /api/currencies/{id}   → Ver moneda (público)
POST /api/currencies       → Crear o actualizar moneda (admin)
```

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/PaymentController.php` | Endpoints de pago |
| `app/Models/Payment.php` | Modelo de pago |
| `app/Models/Currency.php` | Modelo de moneda |
| `app/Services/PaypalService.php` | Integración PayPal |
| `app/Services/StripeService.php` | Integración Stripe |
| `app/Services/WompiService.php` | Integración Wompi |
| `app/Http/Requests/PaymentRequest.php` | Validación |
| `app/Http/Resources/PaymentResource.php` | Serialización |
| `app/Http/Resources/CurrencyResource.php` | Serialización de moneda |
