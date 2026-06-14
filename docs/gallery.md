# Módulo de Galería

Gestión de la galería de imágenes pública mostrada en la landing page. Permite subida en lote, reordenamiento y eliminación de imágenes.

## Índice

1. [Modelo](#modelo)
2. [API Reference](#api-reference)
3. [Notas de implementación](#notas-de-implementación)

---

## Modelo

### `GalleryItem`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid | Clave primaria |
| `caption` | string nullable | Texto descriptivo de la imagen |
| `sort_order` | integer | Orden de aparición en la galería |

**Media:**
Cada `GalleryItem` tiene una colección `image` (una imagen por ítem) gestionada por **Spatie Media Library**. Las imágenes se almacenan en el disco configurado y se sirven por URL.

---

## API Reference

### Listar imágenes (pública)

```
GET /api/gallery
```

Retorna los ítems activos ordenados por `sort_order` ascendente.

**Response:**
```json
{
  "data": [
    {
      "type": "gallery-items",
      "id": "uuid",
      "attributes": {
        "caption": "Volcán Izalco al atardecer",
        "sort_order": 1,
        "image_url": "https://..."
      }
    }
  ]
}
```

---

### Subir imágenes (admin)

Permite subir uno o múltiples archivos en una sola petición.

```
POST /api/gallery
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `images[]` | file | Uno o más archivos de imagen |
| `captions[]` | string nullable | Caption para cada imagen (mismo orden que `images[]`) |

**Response 201:**
```json
{
  "data": [
    {
      "type": "gallery-items",
      "id": "uuid-1",
      "attributes": {
        "caption": "Playa El Tunco",
        "sort_order": 5,
        "image_url": "https://..."
      }
    }
  ]
}
```

Los nuevos ítems se agregan al final del orden existente.

---

### Eliminar imagen (admin)

```
DELETE /api/gallery/{id}
Authorization: Bearer {token}
```

Elimina el ítem y su archivo de media del storage.

---

### Reordenar galería (admin)

```
PATCH /api/gallery/reorder
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "attributes": {
      "order": ["uuid-3", "uuid-1", "uuid-5", "uuid-2"]
    }
  }
}
```

El array `order` contiene los IDs en el nuevo orden deseado. El endpoint asigna `sort_order` secuencial según la posición en el array.

---

## Notas de implementación

- Las imágenes de la galería son independientes de las galerías de tours y vehículos. Cada una tiene su propio almacenamiento y endpoints.
- Los tours y vehículos tienen sus propios endpoints de galería bajo sus respectivos recursos (`/tours/{id}/gallery`, `/transport-vehicles/{id}/gallery`).
- El storage de galería usa el disco `public` para que las URLs sean accesibles directamente.

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Travel/GalleryController.php` | Endpoints de galería |
| `app/Models/GalleryItem.php` | Modelo |
| `app/Http/Resources/GalleryItemResource.php` | Serialización |
