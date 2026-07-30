# VamosPues — Backend API (Laravel)

Documentación técnica del API REST del proyecto VamosPues. Construido con Laravel 13, autenticación OAuth 2.0 vía Laravel Passport y respuestas en formato JSON:API.

## Índice de módulos

| Módulo | Archivo | Descripción |
|--------|---------|-------------|
| Instalador | [installer.md](installer.md) | Instalación limpia y creación del primer administrador (wizard web + comando artisan) |
| Autenticación | [authentication.md](authentication.md) | Login, registro, OAuth social, recuperación de contraseña |
| Usuarios y roles | [user-management.md](user-management.md) | Gestión de usuarios, roles y permisos (RBAC) |
| Tours | [tours.md](tours.md) | Catálogo de tours, categorías, disponibilidad y galería |
| Transporte | [transport.md](transport.md) | Vehículos de transporte, disponibilidad y galería |
| Reservas | [booking-system.md](booking-system.md) | Sistema unificado de reservas: tramos de grupo, extras, opción de vehículo, recogida y cupones |
| Cupones | [coupons.md](coupons.md) | Cupones de descuento (porcentaje/fijo), vigencia y límites de uso |
| Pagos | [payments.md](payments.md) | PayPal, Stripe, Wompi y pagos manuales |
| Facturación DTE | [dte-facturacion-electronica.md](dte-facturacion-electronica.md) | Facturas electrónicas para El Salvador (MH) |
| Configuración | [settings.md](settings.md) | Ajustes generales de la aplicación |
| Consultas personalizadas | [custom-inquiries.md](custom-inquiries.md) | Formulario de cotización a medida |
| Galería | [gallery.md](gallery.md) | Gestión de la galería de imágenes pública |
| Reportes | [reports.md](reports.md) | Estadísticas y resumen de operaciones |
| Finanzas | [finance.md](finance.md) | Rentabilidad por tour: gastos, categorías, guías e ingresos derivados de reservas |
| **Despliegue** | [deployment/README.md](deployment/README.md) | Guías de instalación por proveedor (AWS, GCP, DigitalOcean, Railway, VPS) |

## Documentos internos

| Documento | Archivo | Descripción |
|-----------|---------|-------------|
| Módulos de negocio | [modules.md](modules.md) | Visión general consolidada de los módulos (tours, transporte, reservas, reseñas, suscriptores) |
| Requerimientos — mejoras a reservas | [requerimientos-mejoras-reservas.md](requerimientos-mejoras-reservas.md) | Levantamiento para validar con el cliente (borrador de planificación) |

---

## Stack técnico

| Capa | Tecnología |
|------|-----------|
| Framework | Laravel 13 |
| Autenticación | Laravel Passport (OAuth 2.0) |
| Autorización | Spatie Laravel Permission (RBAC) |
| Media | Spatie Media Library |
| PDF | DOMPDF |
| HTTP externo | Guzzle HTTP |

## Convenciones de API

- **Formato**: JSON:API (`Content-Type: application/vnd.api+json`)
- **Autenticación**: `Authorization: Bearer {token}`
- **Paginación**: `?page[number]=1&page[size]=15`
- **Prefijo de rutas**: `/api/v1/`

### Documento JSON:API y `data.id`

En las peticiones `POST`/`PATCH` el cuerpo debe incluir `data.type` y normalmente `data.attributes`. El campo **`data.id` solo es obligatorio en peticiones `PATCH` a rutas de recurso con parámetro** (p. ej. `PATCH /tours/{tour}`), donde el `id` del documento debe coincidir con el de la URL.

Las acciones sobre recursos _singleton_ del usuario autenticado **no requieren `data.id`**, ya que no llevan parámetro en la URL. Por ejemplo:

- `PATCH /account/profile`
- `PATCH /account/change-password`

## Estructura de respuesta estándar

```json
{
  "data": {
    "type": "resource-type",
    "id": "1",
    "attributes": { ... }
  },
  "meta": {
    "pagination": {
      "total": 50,
      "count": 15,
      "per_page": 15,
      "current_page": 1,
      "total_pages": 4
    }
  }
}
```

## Roles del sistema

| Rol | Acceso |
|-----|--------|
| `super-admin` | Acceso total sin restricciones |
| `admin` | Gestión completa de recursos |
| `guia` | Miembro del equipo con acceso limitado; los gastos se le atribuyen |
| `user` | Acceso propio (perfil, reservas, pagos) |
