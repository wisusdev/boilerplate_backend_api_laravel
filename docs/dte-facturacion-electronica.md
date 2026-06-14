# Facturación Electrónica — El Salvador (DTE)

## Índice

1. [Visión general](#visión-general)
2. [Requisitos previos](#requisitos-previos)
3. [Configuración inicial](#configuración-inicial)
4. [Arquitectura del sistema](#arquitectura-del-sistema)
5. [Base de datos](#base-de-datos)
6. [DteService — referencia](#dteservice--referencia)
7. [API Reference](#api-reference)
8. [Flujo completo DTE](#flujo-completo-dte)
9. [Estructura del DTE (Factura CF Type 03)](#estructura-del-dte-factura-cf-type-03)
10. [Cálculo de IVA](#cálculo-de-iva)
11. [Gestión del certificado digital](#gestión-del-certificado-digital)
12. [Panel de administración](#panel-de-administración)
13. [Estados del DTE](#estados-del-dte)
14. [Catálogos del Ministerio de Hacienda](#catálogos-del-ministerio-de-hacienda)
15. [Errores comunes](#errores-comunes)
16. [Checklist de activación](#checklist-de-activación)

---

## Visión general

El sistema implementa la integración con el **Sistema de Factura Electrónica (FESV)** del **Ministerio de Hacienda de El Salvador**, permitiendo emitir **Documentos Tributarios Electrónicos (DTE)** automáticamente al confirmar una reserva de tour o transporte.

### Tipo de DTE implementado

| Código | Nombre | Uso |
|--------|--------|-----|
| **03** | Factura de Consumidor Final | Ventas a personas naturales sin NIT/NRC (turistas, clientes finales) |

> Para ventas a empresas con NIT/NRC, el tipo **01** (Comprobante de Crédito Fiscal) puede añadirse siguiendo el mismo patrón del tipo 03.

### Endpoints MH utilizados

| Ambiente | URL Base |
|----------|----------|
| Pruebas  | `https://apitest.dtes.mh.gob.sv` |
| Producción | `https://api.dtes.mh.gob.sv` |

| Endpoint | Método | Propósito |
|----------|--------|-----------|
| `/seguridad/auth` | POST | Obtener token bearer |
| `/fesv/recepciondte` | POST | Enviar DTE firmado |

---

## Requisitos previos

Antes de activar la facturación electrónica, el negocio debe tramitar ante el Ministerio de Hacienda:

1. **NIT** — Número de Identificación Tributaria
2. **NRC** — Número de Registro de Contribuyente
3. **Credenciales API** — Usuario y contraseña para autenticarse en el portal FESV
4. **Certificado digital `.p12`** — Emitido por el MH, contiene la llave privada para firmar DTEs
5. **Códigos de catálogo** — Actividad económica, departamento y municipio según los catálogos del MH

---

## Configuración inicial

### 1. Acceder al Panel de Administración

Navegar a: **Admin → Configuración → Factura Electrónica**  
URL: `http://[dominio]/admin/settings` → tab **Factura Electrónica**

### 2. Datos del emisor

Completar todos los campos obligatorios:

```
NIT del emisor:          06140101231234   (sin guiones)
NRC:                     123456
Nombre / Razón Social:   EMPRESA TURISTICA SA DE CV
Nombre Comercial:        MiEmpresa (opcional)
Código de Actividad:     7912             (ver catálogo MH)
Descripción Actividad:   Agencias de viajes y operadores turísticos
Código Establecimiento:  M001
Código Punto de Venta:   P001
Departamento:            06               (06 = San Salvador)
Municipio:               23               (ver catálogo MH)
Dirección:               Calle Arce #123, Col. Escalón
Teléfono:                21234567
Correo:                  facturacion@empresa.com.sv
```

### 3. Credenciales API MH

```
Usuario MH:     usuario_registrado@empresa.com.sv
Contraseña MH:  [contraseña del portal FESV]
Ambiente:       Prueba → para testing | Producción → para facturación real
```

> Las credenciales se almacenan **cifradas** en la base de datos.

### 4. Certificado digital

1. En el tab **Factura Electrónica**, sección **Certificado Digital (.p12)**
2. Seleccionar el archivo `.p12` emitido por el MH
3. Ingresar la contraseña del certificado
4. Clic en **"Cargar y validar certificado"**

El sistema valida el certificado antes de guardarlo. Si la contraseña es incorrecta, muestra error.

El certificado se almacena en: `storage/app/local/dte/certificate.p12`

### 5. Activar el sistema

Una vez configurado todo:

- **Habilitar DTE**: activar el toggle
- **Generar DTE automáticamente**: si está activo, cada booking confirmado genera y envía el DTE automáticamente. Si está inactivo, los DTEs se generan manualmente desde **Admin → Facturas (DTE)**.

---

## Arquitectura del sistema

```
Booking confirmado
        │
        ▼
BookingObserver::createInvoiceIfNeeded()
        │
        ├─ Crea Invoice con dte_status = 'not_generated'
        │
        └─ ¿dte_auto_generate = true?
                │ SÍ
                ▼
        DteService::processDte(invoice)
                │
                ├── 1. buildFacturaCF()     → JSON del DTE
                ├── 2. authenticate()        → token MH
                ├── 3. sign()               → firma con .p12
                ├── 4. send()               → POST /fesv/recepciondte
                └── 5. Invoice update        → sello + dte_status
```

### Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Services/DteService.php` | Lógica central: construcción, firma y envío de DTEs |
| `app/Http/Controllers/Api/Travel/InvoiceController.php` | Endpoints REST para gestión de facturas |
| `app/Models/Invoice.php` | Modelo con campos DTE y constantes de estado |
| `app/Http/Resources/InvoiceResource.php` | Serialización JSON:API |
| `app/Observers/BookingObserver.php` | Trigger automático al confirmar booking |
| `database/migrations/2026_05_31_100001_add_dte_fields_to_invoices_table.php` | Migración DTE |
| `routes/api.php` | Rutas `/invoices` y `/settings/dte-certificate` |

---

## Base de datos

### Tabla `invoices` — campos DTE añadidos

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `dte_type` | varchar(5) | Tipo de DTE: `'03'` = Factura CF |
| `dte_number` | varchar(40) | Número de control: `DTE-03-M001P001-000000000001` |
| `dte_generation_code` | varchar(36) unique | UUID del documento (asignado por el emisor) |
| `dte_seal` | varchar(500) | Sello de recepción emitido por MH |
| `dte_status` | varchar(20) | Estado del ciclo de vida (ver tabla de estados) |
| `receptor_name` | varchar(250) | Nombre del receptor (snapshot al emitir) |
| `receptor_document` | varchar(50) | NIT o DUI del receptor |
| `receptor_email` | varchar(150) | Email del receptor para envío |
| `dte_json` | json | JSON completo del DTE generado |
| `mh_response` | json | Respuesta raw de la API del MH |
| `dte_environment` | varchar(10) | `'00'` = prueba · `'01'` = producción |
| `dte_submitted_at` | timestamp | Cuándo se envió al MH |
| `dte_accepted_at` | timestamp | Cuándo fue aceptado por el MH |

### Campos preexistentes relevantes

| Columna | Descripción |
|---------|-------------|
| `booking_id` | FK → bookings (1:1) |
| `amount` | Monto total de la factura |
| `status` | `pending` → `issued` (cuando DTE es aceptado) |
| `dte_code` | Alias del sello MH (para backward compatibility) |
| `issued_at` | Fecha y hora de emisión |

---

## DteService — referencia

### `processDte(Invoice $invoice): Invoice`

Orquesta el flujo completo. Lanza excepción si el DTE no puede generarse.

```php
$dteService->processDte($invoice);
```

**Precondiciones:**
- `dte_enabled = true` en settings
- `invoice->canGenerateDte()` retorna `true` (estados: `not_generated`, `error`, `rejected`)
- Certificado `.p12` válido en storage
- Credenciales MH configuradas

### `previewDte(Invoice $invoice): array`

Genera el JSON del DTE **sin** enviarlo al MH. Útil para revisión antes de emitir.

```php
$json = $dteService->previewDte($invoice);
```

### `uploadCertificate(string $tempPath, string $password): string`

Valida el `.p12` con la contraseña y lo guarda en storage privado. Retorna el path relativo.

```php
$path = $dteService->uploadCertificate($tempFile, $password);
// → 'dte/certificate.p12'
```

### `buildFacturaCF(Invoice $invoice, Booking $booking): array`

Construye el array JSON del DTE Type 03 con todos los campos requeridos por el MH.

---

## API Reference

### Listar facturas

```
GET /api/invoices
Authorization: Bearer {token}
```

**Query params:**

| Param | Descripción |
|-------|-------------|
| `dte_status` | Filtrar por estado: `not_generated`, `accepted`, `rejected`, `error` |
| `booking_type` | `tour` \| `transport` |
| `status` | Estado de la factura: `pending` \| `issued` |
| `page[number]` | Número de página |

**Response:**
```json
{
  "data": [{
    "type": "invoices",
    "id": "14",
    "attributes": {
      "booking_id": "27",
      "booking_type": "tour",
      "tour_title": "Volcan Santa Ana Sunrise",
      "amount": "65.00",
      "status": "issued",
      "dte_type": "03",
      "dte_number": "DTE-03-M001P001-000000000014",
      "dte_generation_code": "A1B2C3D4-...",
      "dte_seal": "202401...",
      "dte_status": "accepted",
      "dte_environment": "01",
      "dte_submitted_at": "2026-05-31T10:30:00Z",
      "dte_accepted_at": "2026-05-31T10:30:05Z",
      "receptor_name": "Juan Pérez",
      "receptor_document": "01234567-8",
      "receptor_email": "juan@correo.com"
    }
  }]
}
```

---

### Actualizar receptor

```
PATCH /api/invoices/{id}
Authorization: Bearer {token}
Content-Type: application/vnd.api+json
```

```json
{
  "data": {
    "type": "invoices",
    "attributes": {
      "receptor_name": "Juan Pérez",
      "receptor_document": "01234567-8",
      "receptor_email": "juan@correo.com"
    }
  }
}
```

> Actualizar los datos del receptor **antes** de generar el DTE. Una vez enviado al MH, no puede modificarse.

---

### Generar y enviar DTE

```
POST /api/invoices/{id}/generate-dte
Authorization: Bearer {token}
```

**Response exitosa (DTE aceptado):**
```json
{
  "data": {
    "type": "dte-result",
    "id": "14",
    "attributes": {
      "dte_status": "accepted",
      "dte_number": "DTE-03-M001P001-000000000014",
      "dte_seal": "20240115101530...",
      "dte_accepted_at": "2026-05-31T10:30:05Z",
      "mh_response": {
        "estado": "PROCESADO",
        "codigoGeneracion": "A1B2C3D4-...",
        "selloRecibido": "20240115..."
      }
    }
  }
}
```

**Response con error:**
```json
{
  "errors": [{
    "title": "Error DTE",
    "detail": "Error autenticando con MH: 401 — Credenciales inválidas"
  }]
}
```

---

### Preview del JSON DTE (sin enviar)

```
GET /api/invoices/{id}/preview-dte
Authorization: Bearer {token}
```

Retorna el JSON completo del DTE que se enviaría al MH. Útil para revisión y debugging.

---

### Subir certificado .p12

```
POST /api/settings/dte-certificate
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `certificate` | file | Archivo `.p12` o `.pfx` — máx 2 MB |
| `password` | string | Contraseña del certificado |

**Response:**
```json
{
  "data": {
    "type": "dte-certificate",
    "attributes": {
      "path": "dte/certificate.p12",
      "message": "Certificado cargado y validado correctamente."
    }
  }
}
```

---

## Flujo completo DTE

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FLUJO DTE COMPLETO                          │
└─────────────────────────────────────────────────────────────────────┘

 1. BOOKING CONFIRMADO
    Admin hace PATCH /api/bookings/{id} → { status: "confirmed" }
    ↓
    BookingObserver::updated() se dispara
    ↓
    Invoice::firstOrCreate() → dte_status = 'not_generated'

 2. AUTENTICACIÓN MH
    POST https://apitest.dtes.mh.gob.sv/seguridad/auth
    Body: { "user": "xxx", "pwd": "xxx" }
    ← Response: { "body": { "token": "eyJ..." } }

 3. CONSTRUCCIÓN DEL DTE JSON
    DteService::buildFacturaCF()
    ├── identificacion.codigoGeneracion  = UUID (asignado localmente)
    ├── identificacion.numeroControl     = DTE-03-M001P001-000000000001
    ├── emisor                           = datos del negocio (settings)
    ├── receptor                         = datos del cliente (invoice.receptor_*)
    ├── cuerpoDocumento[0].ventaGravada  = monto / 1.13  (base sin IVA)
    └── resumen.tributos[0].valor        = monto - base  (IVA 13%)

 4. FIRMA DIGITAL
    openssl_pkcs12_read(certificate.p12, password)
    hash = SHA256(JSON.stringify(dteJson))
    openssl_sign(hash, signature, privateKey, OPENSSL_ALGO_SHA256)
    signatureBase64 = base64_encode(signature)

 5. ENVÍO AL MH
    POST https://apitest.dtes.mh.gob.sv/fesv/recepciondte
    Headers: Authorization: Bearer {token}
    Body: {
      "ambiente":  "00",
      "idEnvio":   1,
      "version":   1,
      "tipoDte":   "03",
      "documento": "{...DTE con firma...}"
    }

 6. PROCESAMIENTO DE RESPUESTA
    estado = "PROCESADO"  → dte_status = 'accepted'
                             dte_seal   = response.selloRecibido
                             status     = 'issued'
    estado ≠ "PROCESADO"  → dte_status = 'rejected'
                             mh_response guardado para diagnóstico

 7. NOTIFICACIÓN (si DTE aceptado)
    BookingService::notifyConfirmation()
    └── InvoiceCreatedNotification → email al cliente con número DTE
```

---

## Estructura del DTE (Factura CF Type 03)

El JSON enviado al MH tiene la siguiente estructura. Los campos marcados con `*` son calculados automáticamente.

```json
{
  "identificacion": {
    "version": 1,
    "ambiente": "00",                              // "00" prueba / "01" producción
    "tipoDte": "03",
    "numeroControl": "DTE-03-M001P001-000000000001", // * auto-generado
    "codigoGeneracion": "UUID-v4",                 // * UUID único por documento
    "tipoModelo": 1,
    "tipoOperacion": 1,
    "tipoContingencia": null,
    "motivoContigencia": null,
    "fecEmi": "2026-05-31",                        // * fecha actual
    "horEmi": "10:30:00",                          // * hora actual
    "tipoMoneda": "USD"
  },
  "documentoRelacionado": null,
  "emisor": {
    "nit": "06140101231234",                       // configuración
    "nrc": "123456",
    "nombre": "EMPRESA TURISTICA SA DE CV",
    "codActividad": "7912",
    "descActividad": "Agencias de viajes",
    "nombreComercial": "MiEmpresa",
    "tipoEstablecimiento": "02",
    "direccion": {
      "departamento": "06",
      "municipio": "23",
      "complemento": "Calle Arce #123"
    },
    "telefono": "21234567",
    "correo": "facturacion@empresa.com.sv",
    "codEstablecMH": null,
    "codEstablec": "M001",
    "codPuntoVentaMH": null,
    "codPuntoVenta": "P001"
  },
  "receptor": {
    "tipoDocumento": "13",                         // 13=DUI, null=CF anónimo
    "numDocumento": "01234567-8",
    "nrc": null,
    "nombre": "Juan Pérez",
    "codActividad": null,
    "descActividad": null,
    "direccion": null,
    "telefono": null,
    "correo": "juan@correo.com"
  },
  "ventaTercero": null,
  "cuerpoDocumento": [
    {
      "numItem": 1,
      "tipoItem": 2,                               // 2 = Servicio
      "numeroDocumento": null,
      "cantidad": 3,                               // party_size del booking
      "codigo": null,
      "codTributo": null,
      "uniMedida": 99,                             // 99 = Otras unidades
      "descripcion": "Tour — Volcan Santa Ana",
      "precioUni": 19.16,                          // * base/cantidad (sin IVA)
      "montoDescu": 0,
      "ventaNoSuj": 0,
      "ventaExenta": 0,
      "ventaGravada": 57.48,                       // * monto / 1.13
      "tributos": ["20"],                          // 20 = IVA
      "psv": 0,
      "noGravado": 0
    }
  ],
  "resumen": {
    "totalNoSuj": 0,
    "totalExenta": 0,
    "totalGravada": 57.48,
    "subTotalVentas": 57.48,
    "descuEnLinea": 0,
    "descuPorcentaje": 0,
    "totalDescu": 0,
    "tributos": [
      {
        "codigo": "20",
        "descripcion": "Impuesto al Valor Agregado 13%",
        "valor": 7.52                              // * total - base
      }
    ],
    "subTotal": 57.48,
    "ivaPerci1": 0,
    "ivaRete1": 0,
    "reteRenta": 0,
    "montoTotalOperacion": 65.00,                  // invoice.amount
    "totalLetras": "SESENTA Y CINCO DÓLARES CON 00/100",
    "totalIva": 7.52,
    "saldoFavor": 0,
    "condicionOperacion": 1,                       // 1 = Contado
    "pagos": [
      {
        "codigo": "01",                            // 01 = Billetes y monedas
        "montoPago": 65.00,
        "referencia": null,
        "plazo": null,
        "periodo": null
      }
    ],
    "numPagoElectronico": null
  },
  "extension": null,
  "apendice": null
}
```

---

## Cálculo de IVA

El IVA en El Salvador es del **13%** y se asume que los precios configurados en el sistema **incluyen IVA**.

```
Precio total (con IVA):   $65.00
Base imponible:           $65.00 / 1.13  = $57.52
IVA (13% de la base):     $65.00 - $57.52 = $7.48

Verificación:             $57.52 × 1.13 = $65.00 ✓
```

**Código en `DteService::calcularIva()`:**

```php
$base = round($total / 1.13, 2);
$iva  = round($total - $base, 2);
```

> Si su negocio maneja precios **sin IVA**, deberá modificar `buildFacturaCF()` para que `ventaGravada = precio_sin_iva` y `total = precio_sin_iva * 1.13`.

---

## Gestión del certificado digital

### Almacenamiento

El certificado se guarda en el disco `local` (privado, no accesible por HTTP):

```
storage/app/local/dte/certificate.p12
```

> **Nunca** debe almacenarse en el disco `public` ni en el repositorio git. Añadir a `.gitignore`:
> ```
> storage/app/local/dte/
> ```

### Proceso de firma

```php
// 1. Leer el .p12
openssl_pkcs12_read($certContent, $certs, $certPassword);

// 2. SHA256 hash del JSON
$hash = hash('sha256', json_encode($dteJson, JSON_UNESCAPED_UNICODE));

// 3. Firmar con llave privada RSA
openssl_sign($hash, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256);

// 4. Base64
$signatureBase64 = base64_encode($signature);
```

### Renovación del certificado

Cuando el certificado expire, subir el nuevo archivo desde:  
**Admin → Configuración → Factura Electrónica → Certificado Digital**

---

## Panel de administración

### Admin → Facturas (DTE)

URL: `/admin/invoices`

**Funcionalidades:**

| Acción | Descripción |
|--------|-------------|
| Ver resumen | Cards con conteo de aceptadas, sin DTE, con error, total |
| Filtrar | Por `dte_status` y tipo de booking (tour/transport) |
| Ver detalle | Modal con todos los datos del booking, receptor y DTE |
| Editar receptor | Nombre, NIT/DUI, email (modificable antes de generar DTE) |
| Generar DTE | Envía al MH y muestra el resultado en tiempo real |
| Ver JSON DTE | Preview del JSON antes de enviar (útil para debugging) |
| Ver respuesta MH | Muestra el JSON raw del MH en caso de rechazo |

### Admin → Configuración → Factura Electrónica

URL: `/admin/settings` → tab "Factura Electrónica"

Permite gestionar todos los parámetros de configuración sin modificar código.

---

## Estados del DTE

```
                    ┌─────────────────┐
                    │  not_generated  │  Estado inicial de toda factura
                    └────────┬────────┘
                             │ generate-dte
                             ▼
                    ┌─────────────────┐
                    │   generating    │  En proceso (evita doble envío)
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
              ▼                             ▼
    ┌─────────────────┐           ┌─────────────────┐
    │    accepted     │           │    rejected     │
    │  (PROCESADO MH) │           │  (MH rechazó)   │
    └─────────────────┘           └────────┬────────┘
                                           │ retry
                                           ▼
                                  ┌─────────────────┐
                                  │  not_generated  │  (puede reintentarse)
                                  └─────────────────┘

    ┌─────────────────┐
    │     error       │  Error técnico (red, certificado, etc.)
    └─────────────────┘  (puede reintentarse)
```

| Estado | Descripción | ¿Puede reintentarse? |
|--------|-------------|----------------------|
| `not_generated` | Factura sin DTE | ✅ Sí |
| `generating` | En proceso de generación | ⏳ Esperar |
| `signed` | Firmado, no enviado | — (interno) |
| `sent` | Enviado, respuesta pendiente | — (interno) |
| `accepted` | Aceptado por el MH ✓ | ❌ No |
| `rejected` | Rechazado por el MH | ✅ Sí (corregir datos) |
| `error` | Error técnico | ✅ Sí |

---

## Catálogos del Ministerio de Hacienda

### Departamentos más comunes

| Código | Departamento |
|--------|-------------|
| `01` | Ahuachapán |
| `02` | Santa Ana |
| `03` | Sonsonate |
| `04` | Chalatenango |
| `05` | La Libertad |
| `06` | San Salvador |
| `07` | Cuscatlán |
| `08` | La Paz |
| `09` | Cabañas |
| `10` | San Vicente |
| `11` | Usulután |
| `12` | San Miguel |
| `13` | Morazán |
| `14` | La Unión |

### Actividades económicas turísticas (CIIU)

| Código | Descripción |
|--------|-------------|
| `7912` | Actividades de agencias de viajes |
| `7911` | Actividades de operadores turísticos |
| `5510` | Actividades de alojamiento para estancias cortas |
| `5520` | Actividades de camping y parques para casas rodantes |
| `4923` | Transporte de pasajeros por carretera |
| `8230` | Organización de convenciones y eventos |

### Tipos de documento (receptor)

| Código | Descripción |
|--------|-------------|
| `36` | NIT |
| `13` | DUI |
| `03` | Pasaporte |
| `02` | Carnet de residente |

---

## Errores comunes

### `Error autenticando con MH: 401`
**Causa:** Credenciales incorrectas o no registradas en el portal FESV.  
**Solución:** Verificar usuario y contraseña en **Configuración → Factura Electrónica → Credenciales API**.

### `No se pudo leer el certificado .p12. Verifique la contraseña.`
**Causa:** El archivo `.p12` no corresponde o la contraseña es incorrecta.  
**Solución:** Volver a descargar el certificado del portal MH y subirlo con la contraseña correcta.

### `Certificado DTE no encontrado en: dte/certificate.p12`
**Causa:** El certificado no ha sido subido todavía o fue eliminado del storage.  
**Solución:** Subir el certificado desde **Configuración → Factura Electrónica → Certificado Digital**.

### `El DTE no puede generarse en el estado actual: accepted`
**Causa:** Intento de re-generar un DTE ya aceptado por el MH.  
**Solución:** Los DTEs aceptados son inmutables. Si hay un error en los datos, emitir una nota de crédito (Type 05 — no implementado en esta versión).

### DTE rechazado por MH — `RECHAZADO`
**Causa:** Datos inválidos según los catálogos MH (código de actividad inexistente, NIT mal formateado, etc.).  
**Solución:** 
1. Ver la respuesta MH en el modal de detalle de la factura
2. Corregir los datos en configuración
3. Reintentar con el botón **"Generar y enviar DTE"**

### Factura queda en estado `generating` indefinidamente
**Causa:** Error de red o timeout durante el envío.  
**Solución:** Ejecutar en servidor:
```php
// Marcar como error para permitir reintento
Invoice::where('dte_status', 'generating')
    ->where('updated_at', '<', now()->subMinutes(10))
    ->update(['dte_status' => 'error']);
```

---

## Checklist de activación

### Ambiente de pruebas

- [ ] Registrar empresa en portal FESV (pruebas): `https://portaltes.mh.gob.sv`
- [ ] Obtener credenciales de acceso (usuario + contraseña)
- [ ] Descargar certificado `.p12` de prueba
- [ ] Completar datos del emisor en **Admin → Configuración → Factura Electrónica**
- [ ] Cargar certificado `.p12` con contraseña
- [ ] Ingresar credenciales API MH
- [ ] Seleccionar ambiente: **Prueba**
- [ ] Confirmar una reserva y revisar en **Admin → Facturas** si el DTE fue aceptado
- [ ] Verificar en el portal MH que el DTE aparece como "PROCESADO"

### Paso a producción

- [ ] Registrar empresa en portal FESV (producción): `https://portal.mh.gob.sv`
- [ ] Obtener certificado `.p12` de producción
- [ ] En **Admin → Configuración → Factura Electrónica**:
  - [ ] Cambiar ambiente a **Producción**
  - [ ] Reemplazar certificado `.p12` por el de producción
  - [ ] Actualizar credenciales API (pueden ser distintas al ambiente de prueba)
- [ ] Hacer prueba con una reserva real
- [ ] Activar **"Generar DTE automáticamente"** si se desea emisión automática

---

## Referencias

- [Portal FESV — Ministerio de Hacienda SV](https://www.mh.gob.sv/pmh/es/Temas/Tributacion/Factura_Electronica.html)
- [Documentación técnica DTE — MH](https://factura.gob.sv)
- [Catálogo de municipios y departamentos — MH](https://apitest.dtes.mh.gob.sv/catalogo)
- [Catálogo de actividades económicas — MH](https://apitest.dtes.mh.gob.sv/catalogo/actividadesEconomicas)
