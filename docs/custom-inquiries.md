# Módulo de Consultas Personalizadas

Permite a clientes enviar solicitudes de viaje a medida cuando los tours estándar no se ajustan a sus necesidades. El formulario es accesible tanto para usuarios anónimos como autenticados.

## Índice

1. [Modelo](#modelo)
2. [API Reference](#api-reference)
3. [Flujo del proceso](#flujo-del-proceso)

---

## Modelo

### `CustomInquiry`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `user_id` | uuid nullable FK | Usuario autenticado (null si es anónimo) |
| `preferred_destinations` | json array | Lista de destinos de interés |
| `travel_start_date` | date | Fecha aproximada de inicio del viaje |
| `travel_end_date` | date | Fecha aproximada de fin del viaje |
| `budget_min` | decimal(12,2) nullable | Presupuesto mínimo |
| `budget_max` | decimal(12,2) nullable | Presupuesto máximo |
| `travelers_count` | integer | Número de viajeros |
| `currency_code` | char(3) | Moneda del presupuesto. Validado contra `currencies.code`. |
| `message` | text | Descripción libre de lo que el cliente busca |
| `status` | string | `pending` \| `reviewed` \| `closed` |

**Relaciones:**
- `belongsTo(User, nullable)` — usuario que envió la consulta (si estaba autenticado)

---

## API Reference

### Crear consulta (pública)

Accesible sin autenticación. Si el usuario está autenticado, la consulta se asocia a su cuenta automáticamente.

```
POST /api/custom-inquiries
Content-Type: application/vnd.api+json
```

```json
{
  "data": {
    "type": "custom-inquiries",
    "attributes": {
      "preferred_destinations": ["Suchitoto", "Ruta de las Flores", "El Imposible"],
      "travel_start_date": "2026-08-10",
      "travel_end_date": "2026-08-17",
      "budget_min": 500,
      "budget_max": 1200,
      "travelers_count": 4,
      "currency_code": "USD",
      "message": "Somos una familia con dos niños. Nos interesa naturaleza y cultura. Sin actividades de alto riesgo."
    }
  }
}
```

**Response 201:**
```json
{
  "data": {
    "type": "custom-inquiries",
    "id": "12",
    "attributes": {
      "status": "pending",
      "preferred_destinations": ["Suchitoto", "Ruta de las Flores"],
      "travel_start_date": "2026-08-10",
      "travel_end_date": "2026-08-17",
      "budget_min": "500.00",
      "budget_max": "1200.00",
      "travelers_count": 4,
      "currency_code": "USD",
      "message": "...",
      "created_at": "2026-06-07T..."
    }
  }
}
```

---

### Listar consultas (admin)

```
GET /api/custom-inquiries
Authorization: Bearer {token} (requiere admin)
```

**Query params:**

| Param | Descripción |
|-------|-------------|
| `filter[status]` | `pending` \| `reviewed` \| `closed` |
| `filter[date_from]` | Fecha de creación desde |
| `filter[date_to]` | Fecha de creación hasta |
| `page[number]` | Número de página |

---

### Ver consulta

```
GET /api/custom-inquiries/{id}
Authorization: Bearer {token} (requiere admin)
```

---

## Flujo del proceso

```
1. Cliente envía consulta (POST /api/custom-inquiries)
   → status: pending
   → Notificación por email al equipo de administración

2. Admin revisa en panel (Admin → Consultas personalizadas)
   → Puede ver destinos, fechas, presupuesto y mensaje

3. Admin responde al cliente fuera del sistema
   (por email o teléfono — no hay módulo de respuesta in-app en esta versión)

4. Admin actualiza el estado:
   → reviewed: ya fue atendida
   → closed: proceso completado
```

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/CustomInquiryController.php` | Endpoints |
| `app/Models/CustomInquiry.php` | Modelo |
| `app/Http/Requests/CustomInquiryRequest.php` | Validación |
| `app/Http/Resources/CustomInquiryResource.php` | Serialización |
| `app/Services/CustomInquiryService.php` | Lógica de negocio y notificaciones |
