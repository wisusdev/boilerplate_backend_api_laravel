# VamosPues — Backend API (Laravel)

Documentación técnica del API REST del proyecto VamosPues. Construido con Laravel 13, autenticación OAuth 2.0 vía Laravel Passport y respuestas en formato JSON:API.

## Índice de módulos

| Módulo | Archivo | Descripción |
|--------|---------|-------------|
| Autenticación | [authentication.md](authentication.md) | Login, registro, OAuth social, recuperación de contraseña |
| Usuarios y roles | [user-management.md](user-management.md) | Gestión de usuarios, roles y permisos (RBAC) |
| Tours | [tours.md](tours.md) | Catálogo de tours, categorías, disponibilidad y galería |
| Transporte | [transport.md](transport.md) | Vehículos de transporte, disponibilidad y galería |
| Reservas | [booking-system.md](booking-system.md) | Sistema unificado de reservas (tours y transporte) |
| Pagos | [payments.md](payments.md) | PayPal, Stripe, Wompi y pagos manuales |
| Facturación DTE | [dte-facturacion-electronica.md](dte-facturacion-electronica.md) | Facturas electrónicas para El Salvador (MH) |
| Configuración | [settings.md](settings.md) | Ajustes generales de la aplicación |
| Consultas personalizadas | [custom-inquiries.md](custom-inquiries.md) | Formulario de cotización a medida |
| Galería | [gallery.md](gallery.md) | Gestión de la galería de imágenes pública |
| Reportes | [reports.md](reports.md) | Estadísticas y resumen de operaciones |
| **Despliegue** | [deployment/README.md](deployment/README.md) | Guías de instalación por proveedor (AWS, GCP, DigitalOcean, Railway, VPS) |

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
- **Prefijo de rutas**: `/api/`

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
| `user` | Acceso propio (perfil, reservas, pagos) |
