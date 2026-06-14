# Módulo de Reportes

Proporciona estadísticas y resumen de operaciones del negocio para el panel de administración.

## Índice

1. [API Reference](#api-reference)
2. [Métricas disponibles](#métricas-disponibles)

---

## API Reference

### Resumen general

```
GET /api/reports/overview
Authorization: Bearer {token} (requiere admin)
```

**Query params:**

| Param | Descripción |
|-------|-------------|
| `start_date` | Fecha inicio del período (`Y-m-d`) |
| `end_date` | Fecha fin del período (`Y-m-d`) |

Si no se especifica rango, retorna estadísticas del mes actual.

**Response:**
```json
{
  "data": {
    "type": "reports",
    "attributes": {
      "bookings": {
        "total": 145,
        "pending": 12,
        "confirmed": 128,
        "cancelled": 5,
        "by_type": {
          "tour": 98,
          "transport": 47
        }
      },
      "revenue": {
        "total": 9850.00,
        "currency_code": "USD",
        "by_gateway": {
          "paypal": 4200.00,
          "stripe": 3500.00,
          "wompi": 1500.00,
          "manual": 650.00
        }
      },
      "invoices": {
        "total": 128,
        "dte_accepted": 120,
        "dte_pending": 5,
        "dte_error": 3
      },
      "custom_inquiries": {
        "total": 23,
        "pending": 8,
        "reviewed": 15
      },
      "top_tours": [
        { "title": "Volcán Santa Ana", "bookings": 34 },
        { "title": "Ruta de las Flores", "bookings": 28 }
      ]
    }
  }
}
```

---

## Métricas disponibles

| Métrica | Descripción |
|---------|-------------|
| Total de reservas | Conteo por estado y tipo (tour/transporte) |
| Ingresos | Suma de pagos confirmados, desglosada por gateway |
| Estado DTE | Conteo de facturas por estado de DTE |
| Consultas personalizadas | Conteo por estado |
| Top tours | Tours con más reservas en el período |

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/ReportController.php` | Endpoint de reportes |
| `app/Services/ReportService.php` | Cálculos y consultas de agregación |
