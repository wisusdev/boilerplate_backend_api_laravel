# Módulo de Usuarios y Control de Acceso (RBAC)

Gestión de usuarios del sistema, roles y permisos. Basado en **Spatie Laravel Permission** con UUIDs como clave primaria en todas las entidades.

## Índice

1. [Modelos](#modelos)
2. [Usuarios — API](#usuarios--api)
3. [Cuenta propia — API](#cuenta-propia--api)
4. [Roles — API](#roles--api)
5. [Permisos — API](#permisos--api)
6. [Eliminación de cuenta](#eliminación-de-cuenta)

---

## Modelos

### `User`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid | Clave primaria |
| `name` | string | Nombre completo |
| `email` | string unique | Correo electrónico |
| `phone` | string nullable | Teléfono |
| `language` | string | Idioma preferido (p.ej. `es`, `en`) |
| `avatar` | string nullable | URL de avatar almacenado como WebP |
| `email_verified_at` | timestamp nullable | Fecha de verificación de correo |
| `deleted_at` | timestamp nullable | Soft delete |

**Traits:** `SoftDeletes`, `MustVerifyEmail`, `HasRoles` (Spatie), `HasApiTokens` (Passport)

### `Role`

Extiende `Spatie\Permission\Models\Role`. Usa UUID como PK.

**Roles predefinidos:** `super-admin`, `admin`, `user`

### `Permission`

Extiende `Spatie\Permission\Models\Permission`. Usa UUID como PK.

---

## Usuarios — API

Requiere rol `admin` o `super-admin`.

### Listar usuarios

```
GET /api/users
Authorization: Bearer {token}
```

**Query params:**

| Param | Descripción |
|-------|-------------|
| `filter[search]` | Buscar por nombre o email |
| `filter[role]` | Filtrar por rol |
| `sort` | Campo de ordenamiento (`name`, `email`, `created_at`) |
| `page[number]` | Número de página |
| `page[size]` | Tamaño de página |

---

### Crear usuario

```
POST /api/users
Content-Type: application/vnd.api+json
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "users",
    "attributes": {
      "name": "Nombre Completo",
      "email": "usuario@ejemplo.com",
      "password": "contraseña",
      "roles": ["admin"]
    }
  }
}
```

---

### Ver usuario

```
GET /api/users/{uuid}
Authorization: Bearer {token}
```

---

### Actualizar usuario

```
PATCH /api/users/{uuid}
Authorization: Bearer {token}
```

```json
{
  "data": {
    "type": "users",
    "attributes": {
      "name": "Nuevo Nombre",
      "roles": ["user"]
    }
  }
}
```

Los roles se sincronizan: los roles no incluidos en el array se revocan.

---

### Eliminar usuario (soft delete)

```
DELETE /api/users/{uuid}
Authorization: Bearer {token}
```

Al eliminar:
- Se aplica soft delete (el registro queda en base de datos con `deleted_at`).
- Se revocan todos los tokens activos del usuario.
- Se elimina el avatar del storage.

---

## Cuenta propia — API

Endpoints para que el usuario autenticado gestione su propio perfil. No requieren rol de admin.

### Ver perfil

```
GET /api/account/profile
Authorization: Bearer {token}
```

### Actualizar perfil

```
PATCH /api/account/profile
Authorization: Bearer {token}
Content-Type: application/vnd.api+json
```

```json
{
  "data": {
    "attributes": {
      "name": "Nuevo Nombre",
      "phone": "+503 7000-0000",
      "language": "es",
      "avatar": "data:image/jpeg;base64,/9j/4AAQ..."
    }
  }
}
```

- El avatar se envía como base64 y se convierte a **WebP** antes de almacenarse.
- Si se cambia el email, se envía un nuevo correo de verificación.

### Cambiar contraseña

```
PATCH /api/account/change-password
Authorization: Bearer {token}
```

```json
{
  "data": {
    "attributes": {
      "current_password": "actual",
      "password": "nueva",
      "password_confirmation": "nueva"
    }
  }
}
```

---

## Eliminación de cuenta

La eliminación es un proceso de dos pasos para evitar accidentes.

### Paso 1 — Solicitar eliminación

```
POST /api/account/delete-account
Authorization: Bearer {token}
```

Envía un correo con un token válido por **6 horas** para confirmar la eliminación.

### Paso 2 — Confirmar eliminación

```
DELETE /api/account/delete-account-verify
Authorization: Bearer {token}
```

```json
{
  "data": {
    "attributes": {
      "token": "token_del_correo"
    }
  }
}
```

Al confirmar se elimina la cuenta permanentemente (no soft delete).

---

## Roles — API

Requiere rol `admin` o `super-admin`.

### Listar roles

```
GET /api/roles
Authorization: Bearer {token}
```

### Crear rol

```
POST /api/roles
```

```json
{
  "data": {
    "type": "roles",
    "attributes": {
      "name": "editor",
      "permissions": ["tours.create", "tours.update"]
    }
  }
}
```

### Actualizar rol

```
PATCH /api/roles/{uuid}
```

Los permisos se sincronizan igual que los roles en usuarios.

### Eliminar rol

```
DELETE /api/roles/{uuid}
```

**Restricciones:**
- No se puede eliminar `super-admin` ni `admin`.
- No se puede eliminar un rol que tenga usuarios asignados.

---

## Permisos — API

### Listar permisos

```
GET /api/permissions
Authorization: Bearer {token}
```

Retorna todos los permisos disponibles en el sistema. Los permisos son definidos en el seeder y no pueden crearse vía API.

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Base/UserController.php` | CRUD de usuarios |
| `app/Http/Controllers/Api/Base/AccountController.php` | Gestión de cuenta propia |
| `app/Http/Controllers/Api/Base/RolesController.php` | CRUD de roles |
| `app/Http/Controllers/Api/Base/PermissionsController.php` | Listado de permisos |
| `app/Models/User.php` | Modelo de usuario |
| `app/Http/Requests/AccountUpdateRequest.php` | Validación de actualización de perfil |
| `app/Http/Resources/ProfileResource.php` | Serialización del perfil |
| `database/seeders/UserSeeder.php` | Usuarios iniciales |
