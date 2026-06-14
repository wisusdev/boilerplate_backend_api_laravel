# Módulo de Configuración

Almacena la configuración global de la aplicación como pares clave-valor en la base de datos. Permite modificar el comportamiento del sistema sin tocar código ni variables de entorno.

## Índice

1. [Modelo](#modelo)
2. [Categorías de configuración](#categorías-de-configuración)
3. [API Reference](#api-reference)
4. [Seguridad de credenciales](#seguridad-de-credenciales)

---

## Modelo

### `Setting`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid | Clave primaria |
| `key` | string unique | Identificador de la configuración |
| `value` | json | Valor (puede ser string, número, objeto o array) |

Los valores se leen y escriben como JSON. El campo `value` almacena el dato nativo (string, boolean, object, etc.) serializado.

---

## Categorías de configuración

### `app`

Configuración general de la aplicación.

| Clave | Tipo | Descripción |
|-------|------|-------------|
| `app.name` | string | Nombre del negocio |
| `app.tagline` | string | Slogan o descripción corta |
| `app.logo` | string | URL del logo |
| `app.contact_email` | string | Correo de contacto |
| `app.contact_phone` | string | Teléfono de contacto |
| `app.address` | string | Dirección física |
| `app.social_links` | object | URLs de redes sociales (facebook, instagram, etc.) |
| `app.timezone` | string | Zona horaria (p.ej. `America/El_Salvador`) |
| `app.max_daily_bookings` | integer nullable | Límite de reservas de tour por día |

---

### `payment_gateway`

Credenciales de pasarelas de pago. Las credenciales sensibles se almacenan **cifradas**.

| Clave | Descripción |
|-------|-------------|
| `payment_gateway.paypal.client_id` | PayPal Client ID |
| `payment_gateway.paypal.client_secret` | PayPal Secret (cifrado) |
| `payment_gateway.paypal.mode` | `sandbox` \| `live` |
| `payment_gateway.stripe.publishable_key` | Stripe Publishable Key |
| `payment_gateway.stripe.secret_key` | Stripe Secret Key (cifrado) |
| `payment_gateway.wompi.public_key` | Wompi Public Key |
| `payment_gateway.wompi.private_key` | Wompi Private Key (cifrado) |
| `payment_gateway.bank_transfer.enabled` | Boolean — activa transferencia bancaria |
| `payment_gateway.bank_transfer.account_info` | Datos de cuenta bancaria |
| `payment_gateway.cash.enabled` | Boolean — activa pago en efectivo |

---

### `social_auth_services`

Credenciales para OAuth social.

| Clave | Descripción |
|-------|-------------|
| `social_auth_services.google.enabled` | Boolean |
| `social_auth_services.google.client_id` | Google OAuth Client ID |
| `social_auth_services.google.client_secret` | Google OAuth Secret (cifrado) |
| `social_auth_services.facebook.enabled` | Boolean |
| `social_auth_services.facebook.app_id` | Facebook App ID |
| `social_auth_services.facebook.app_secret` | Facebook App Secret (cifrado) |

---

### `dte`

Configuración del sistema de facturación electrónica (DTE) de El Salvador. Ver [dte-facturacion-electronica.md](dte-facturacion-electronica.md) para el detalle completo.

| Clave | Descripción |
|-------|-------------|
| `dte.enabled` | Boolean — activa el sistema DTE |
| `dte.auto_generate` | Boolean — genera DTE automáticamente al confirmar reserva |
| `dte.environment` | `00` = prueba, `01` = producción |
| `dte.nit` | NIT del emisor |
| `dte.nrc` | NRC del emisor |
| `dte.company_name` | Razón social |
| `dte.trade_name` | Nombre comercial |
| `dte.activity_code` | Código de actividad económica (MH) |
| `dte.activity_description` | Descripción de actividad |
| `dte.establishment_code` | Código de establecimiento (ej. `M001`) |
| `dte.point_of_sale_code` | Código de punto de venta (ej. `P001`) |
| `dte.department_code` | Código de departamento (ej. `06` para San Salvador) |
| `dte.municipality_code` | Código de municipio |
| `dte.address` | Dirección del establecimiento |
| `dte.phone` | Teléfono del emisor |
| `dte.email` | Correo del emisor |
| `dte.mh_username` | Usuario API del Ministerio de Hacienda |
| `dte.mh_password` | Contraseña API MH (cifrado) |
| `dte.certificate_path` | Ruta interna del certificado `.p12` |
| `dte.certificate_password` | Contraseña del certificado (cifrado) |

---

## API Reference

### Obtener configuración

```
GET /api/settings
Authorization: Bearer {token}
```

- Los usuarios regulares reciben únicamente las claves públicas (nombre, logo, social links, etc.).
- Los admins reciben todas las claves, **excepto** las credenciales cifradas, que se retornan como `"***"` o con indicador `has_value: true`.

---

### Actualizar configuración

```
PATCH /api/settings
Content-Type: application/vnd.api+json
Authorization: Bearer {token} (requiere admin)
```

```json
{
  "data": {
    "type": "settings",
    "attributes": {
      "key": "app",
      "value": {
        "name": "VamosPues",
        "tagline": "Explora El Salvador",
        "contact_email": "hola@vamospues.sv",
        "timezone": "America/El_Salvador",
        "max_daily_bookings": 50
      }
    }
  }
}
```

Se puede actualizar una categoría completa o claves individuales. Las claves no incluidas en el body no se modifican.

---

### Subir logo

```
POST /api/settings/logo
Content-Type: multipart/form-data
Authorization: Bearer {token}

logo: [archivo de imagen]
```

Almacena el logo en el storage público y actualiza `app.logo`.

---

### Subir certificado DTE

```
POST /api/settings/dte-certificate
Content-Type: multipart/form-data
Authorization: Bearer {token}

certificate: [archivo .p12]
password: contraseña_del_certificado
```

Valida el certificado antes de guardarlo. Ver [dte-facturacion-electronica.md](dte-facturacion-electronica.md).

---

## Seguridad de credenciales

Las claves sensibles (secrets, passwords) se cifran con el trait `EncryptsCredentials` antes de persistirse en base de datos. El cifrado usa la `APP_KEY` de Laravel.

Las credenciales cifradas **nunca se retornan en texto plano** por la API. La respuesta incluye un indicador de si el valor está configurado:

```json
{
  "paypal": {
    "client_id": "AaBbCc...",
    "client_secret": "***",
    "has_secret": true
  }
}
```

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Base/SettingsController.php` | Endpoints de configuración |
| `app/Models/Setting.php` | Modelo de configuración |
| `config/services.php` | Lee credenciales desde Settings en runtime |
