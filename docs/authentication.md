# Módulo de Autenticación

Gestiona el ciclo de vida de sesiones de usuario: registro, login, OAuth social, verificación de correo y recuperación de contraseña. Utiliza **Laravel Passport** (OAuth 2.0) para emitir tokens Bearer.

## Índice

1. [Rutas](#rutas)
2. [Login](#login)
3. [Registro](#registro)
4. [Recuperación de contraseña](#recuperación-de-contraseña)
5. [Verificación de correo](#verificación-de-correo)
6. [OAuth social](#oauth-social)
7. [Refresh y logout](#refresh-y-logout)
8. [Límite de dispositivos](#límite-de-dispositivos)
9. [Rate limiting](#rate-limiting)

---

## Rutas

| Método | Ruta | Controlador | Auth |
|--------|------|-------------|------|
| POST | `/api/auth/login` | `LoginController` | No |
| POST | `/api/auth/register` | `RegisterController` | No |
| POST | `/api/auth/forgot-password` | `ForgotController` | No |
| POST | `/api/auth/reset-password` | `ForgotController` | No |
| POST | `/api/auth/logout` | `LogoutController` | Sí |
| POST | `/api/auth/refresh-token` | `RefreshTokenController` | Sí |
| GET | `/api/auth/email/verify/{id}/{hash}` | `VerifyEmailController` | Firmada |
| POST | `/api/auth/email/resend` | `VerifyEmailController` | Sí |
| POST | `/api/auth/verify-social-token` | `SocialAuthController` | No |

---

## Login

```
POST /api/auth/login
Content-Type: application/vnd.api+json
```

**Body:**
```json
{
  "data": {
    "attributes": {
      "email": "usuario@ejemplo.com",
      "password": "contraseña",
      "device_name": "Chrome — MacOS"
    }
  }
}
```

**Response 200:**
```json
{
  "data": {
    "type": "auth",
    "attributes": {
      "token": "eyJ...",
      "expires_at": "2026-06-14T10:00:00Z",
      "user": {
        "id": "uuid",
        "name": "Nombre",
        "email": "usuario@ejemplo.com",
        "roles": ["user"]
      }
    }
  }
}
```

El token tiene una validez de **1 semana**. Incluir en cada petición autenticada como:
```
Authorization: Bearer {token}
```

---

## Registro

```
POST /api/auth/register
```

**Body:**
```json
{
  "data": {
    "attributes": {
      "name": "Nombre Completo",
      "email": "nuevo@ejemplo.com",
      "password": "contraseña",
      "password_confirmation": "contraseña"
    }
  }
}
```

Al registrarse:
- Se asigna el rol `user` automáticamente.
- Se envía un correo de verificación al email proporcionado.
- El usuario puede autenticarse antes de verificar, pero algunos endpoints pueden requerir email verificado.

---

## Recuperación de contraseña

### Solicitar enlace de reseteo

```
POST /api/auth/forgot-password
```

```json
{
  "data": {
    "attributes": {
      "email": "usuario@ejemplo.com"
    }
  }
}
```

Se envía un correo con un enlace válido por **6 horas**.

### Resetear contraseña

```
POST /api/auth/reset-password
```

```json
{
  "data": {
    "attributes": {
      "token": "token_del_correo",
      "email": "usuario@ejemplo.com",
      "password": "nueva_contraseña",
      "password_confirmation": "nueva_contraseña"
    }
  }
}
```

Al resetear:
- Se valida que el token exista y no haya expirado.
- Se **aplica la nueva contraseña** al usuario (encriptada por el cast `hashed` del modelo) y se elimina el token usado.
- Se envía al usuario una notificación de confirmación (`PasswordChangeNotification`) avisando que su contraseña fue restablecida.

---

## Verificación de correo

### Verificar con enlace del correo

```
GET /api/auth/email/verify/{id}/{hash}?expires=...&signature=...
```

Ruta firmada generada automáticamente por Laravel. El frontend redirecciona al usuario a esta URL desde el correo.

### Reenviar correo de verificación

```
POST /api/auth/email/resend
Authorization: Bearer {token}
```

---

## OAuth social

Soporta **Google** y **Facebook**. El cliente obtiene el access token del proveedor y lo valida en el backend.

```
POST /api/auth/verify-social-token
```

```json
{
  "data": {
    "attributes": {
      "provider": "google",
      "token": "access_token_del_proveedor"
    }
  }
}
```

**Comportamiento:**
- Si el usuario ya existe (por email), se autentica.
- Si no existe, se crea automáticamente y se le asigna el rol `user`.
- No requiere verificación de correo (el proveedor ya lo verificó).

Los proveedores habilitados se configuran en **Admin → Configuración → Autenticación social**.

---

## Refresh y logout

### Renovar token

```
POST /api/auth/refresh-token
Authorization: Bearer {token}
```

Revoca el token actual y emite uno nuevo con validez de 1 semana.

### Cerrar sesión

```
POST /api/auth/logout
Authorization: Bearer {token}
```

Revoca únicamente el token actual. Las demás sesiones activas del usuario no se ven afectadas.

---

## Límite de dispositivos

Si el ajuste `auth.limit_auth_devices` está habilitado, el sistema controla cuántos tokens activos simultáneos puede tener un usuario. Cuando se supera el límite, el login falla con un error 422.

Los dispositivos activos se registran en la tabla `device_infos` (modelo `DeviceInfo`), que incluye nombre del dispositivo y timestamps.

---

## Rate limiting

Los endpoints sensibles de autenticación aplican límites de peticiones para mitigar fuerza bruta y el envío masivo de correos. Al excederse, responden con **HTTP 429 (Too Many Requests)**. Los límites están **desactivados en el entorno `testing`**.

| Endpoint | Throttle | Límite | Clave |
|----------|----------|--------|-------|
| `POST /auth/login` | `auth` | 6 / min | IP |
| `POST /auth/reset-password` | `auth` | 6 / min | IP |
| `POST /auth/verify-social-token` | `auth` | 6 / min | IP |
| `POST /auth/register` | `auth-register` | 5 / min + 20 / hora | IP |
| `POST /auth/forgot-password` | `auth-forgot` | 3 / min + 10 / hora | IP |
| `POST /auth/email/resend` | `email-resend` | 2 / min + 6 / hora | por usuario (id) |

> Otros formularios públicos (p. ej. consultas personalizadas / contacto) usan el throttle `forms` (8 / min + 40 / hora). Ver [custom-inquiries.md](custom-inquiries.md).

---

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Api/Auth/LoginController.php` | Login con límite de dispositivos |
| `app/Http/Controllers/Api/Auth/RegisterController.php` | Registro con envío de verificación |
| `app/Http/Controllers/Api/Auth/ForgotController.php` | Recuperación de contraseña |
| `app/Http/Controllers/Api/Auth/SocialAuthController.php` | OAuth Google/Facebook |
| `app/Http/Controllers/Api/Auth/VerifyEmailController.php` | Verificación de email |
| `app/Http/Controllers/Api/Auth/RefreshTokenController.php` | Renovación de token |
| `app/Http/Requests/LoginRequest.php` | Validación de login |
| `app/Http/Requests/RegisterRequest.php` | Validación de registro |
| `app/Http/Resources/LoginResource.php` | Serialización de respuesta de login |
