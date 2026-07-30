# RBAC granular para VamosPués

> **Estado: ✅ IMPLEMENTADO y verificado** (223 tests backend en verde, build frontend OK).
>
> ## Despliegue (servidor de desarrollo GCP)
> Tras hacer pull en la VM, correr los seeders (idempotentes):
> ```bash
> php artisan db:seed --class=PermissionSeeder --force
> php artisan db:seed --class=RoleSeeder --force
> php artisan route:clear && php artisan config:clear && php artisan permission:cache-reset
> ```
> El rol `admin` recibe automáticamente los 63 permisos, así que las cuentas admin
> existentes conservan acceso total. Para tener el tier god inmutable, asignar el
> rol `superadmin` a una cuenta: `User::find(id)->assignRole('superadmin')`.
>
> **Vista "Mis gastos" (`/admin/expenses`):** pantalla para que un `guia` liste y
> registre sus propios gastos (con recibo opcional). El backend restringe listado,
> creación y recibo al `guide_id` propio de quien no tiene `expenses:view-all`.
> El ítem de menú se oculta a admin/finanzas/superadmin (usan Finanzas).

---

## Plan original (referencia)

Objetivo: reemplazar el gateo coarse `role:admin|super-admin` del dominio Travel por
permisos granulares, unificar el nombre del rol super-admin y dar al rol `guia` acceso
real. La propiedad de datos (ownership) ya funciona y no se toca.

---

## 1. Catálogo de permisos (convención `modulo:accion`)

### Base (ya existen — se conservan)
```
roles:index  roles:create  roles:store  roles:show  roles:edit  roles:update  roles:delete
permissions:index  permissions:by-role
users:index  users:create  users:store  users:show  users:edit  users:update  users:delete
```

### Nuevos — dominio Travel

| Módulo | Permisos nuevos | Rutas que gatea |
|---|---|---|
| Tours | `tours:store` `tours:update` `tours:media` | POST /tours, PATCH /tours/{}, featured-image, gallery upload/destroy |
| Transporte | `transport-vehicles:store` `transport-vehicles:update` `transport-vehicles:media` | POST/PATCH + media de transport-vehicles |
| Categorías de tour | `tour-categories:store` `tour-categories:update` `tour-categories:delete` | POST/PATCH/DELETE tour-categories |
| Monedas | `currencies:store` | POST /currencies |
| Testimonios (reviews) | `reviews:store` `reviews:update` `reviews:delete` | POST/PATCH/DELETE reviews |
| Reseñas de producto | `product-reviews:moderate` | GET /product-reviews/admin |
| Cupones | `coupons:index` `coupons:store` `coupons:update` `coupons:delete` | CRUD /coupons |
| Finanzas | `finance:view` | GET /finance/summary |
| Categorías de gasto | `expense-categories:index` `expense-categories:store` `expense-categories:update` `expense-categories:delete` | CRUD /expense-categories |
| Gastos | `expenses:index` `expenses:store` `expenses:update` `expenses:delete` | CRUD /expenses + receipt |
| Facturas / DTE | `invoices:index` `invoices:show` `invoices:update` `invoices:generate-dte` | /invoices, generate-dte, preview-dte, dte-certificate |
| Ajustes | `settings:update` | PATCH /settings, logo, about-image |
| Reportes | `reports:view` | GET /reports/overview |
| Galería | `gallery:store` `gallery:delete` `gallery:reorder` | write de /gallery |
| Suscriptores | `subscribers:index` `subscribers:update` `subscribers:delete` | admin de /subscribers |
| Consultas custom | `custom-inquiries:index` `custom-inquiries:show` | admin de /custom-inquiries |
| Guías | `guides:index` `guides:store` `guides:delete` | gestión de /guides |
| Reservas (admin) | `bookings:view-all` | ver TODAS las reservas (no solo propias) |
| Pagos (admin) | `payments:view-all` | ver TODOS los pagos (no solo propios) |

Total nuevos: ~45 permisos.

---

## 2. Roles y presets

| Rol | Alcance |
|---|---|
| `superadmin` | **God mode** vía `Gate::before` — pasa cualquier check sin depender de filas de permiso. Inmutable (protegido de borrado). |
| `admin` | Todos los permisos (grant-all en el seeder). Rol editable "todo incluido". |
| `finanzas` | `finance:view`, `expenses:*`, `expense-categories:*`, `invoices:*`, `reports:view`, `payments:view-all` |
| `editor` | `tours:*`, `transport-vehicles:*`, `tour-categories:*`, `currencies:store`, `reviews:*`, `product-reviews:moderate`, `gallery:*` |
| `guia` | `expenses:index` + `expenses:store` **con scope propio** (solo `guide_id = su id`) |
| `user` | Sin permisos (cliente). Solo sus propias reservas/pagos vía ownership existente. |

---

## 3. Unificación del nombre super-admin

Adoptar **`superadmin`** (sin guion) para alinear con el frontend (`App.tsx:48`).
Cambiar `super-admin` → `superadmin` en backend (8 archivos):
`routes/api.php`, `BookingController`, `PaymentController`, `ProductReviewController`,
`SettingsController`, `RolesController`, `InstallService`.

---

## 4. Archivos a tocar

### Backend
1. **`database/seeders/PermissionSeeder.php`** — añadir los ~45 permisos nuevos al array.
2. **`database/seeders/RoleSeeder.php`** — crear roles `superadmin`, `finanzas`, `editor`; asignar presets; admin=all; guia con sus 2 permisos.
3. **`routes/api.php`** — reemplazar los grupos `->middleware('role:admin|super-admin')` por `->middleware('permission:<perm>')` por ruta (sección Travel, líneas 92-259).
4. **`app/Providers/AuthServiceProvider.php`** — `Gate::before(fn($u) => $u->hasRole('superadmin') ? true : null)`.
5. **Controladores con `hasRole(['admin','super-admin'])`** — cambiar spelling y, donde aplica, sustituir por check de permiso:
   - `BookingController` (index/show → `bookings:view-all`)
   - `PaymentController` (index/show → `payments:view-all`)
   - `ProductReviewController`, `SettingsController`, `RolesController`, `InstallService` (solo spelling).
6. **`app/Http/Controllers/Api/Travel/ExpenseController.php`** — scope de propiedad para `guia`: en index filtrar `guide_id = user->id` y en store forzar `guide_id = user->id` cuando el usuario NO tenga permiso admin de gastos.
7. **`app/Http/Controllers/Api/Base/RolesController.php:76`** — proteger `superadmin` de borrado (actualizar string).

*Kernel: sin cambios* — los alias `permission` / `role_or_permission` ya están registrados.

### Frontend (para materializar la granularidad en la UI)
8. **`AdminLayout.tsx`** (`NAV_ITEMS`) — filtrar cada ítem del menú por `hasPermission(...)`.
9. **`App.tsx`** — mantener la puerta `/admin` (role admin|superadmin) y opcionalmente gatear rutas hijas por permiso.
10. Cablear el `hasPermission()` ya existente en `AuthContext` (hoy definido pero sin uso). El login ya envía `permissions`, así que no hay cambio de contrato.
11. Nombre de rol: el frontend ya usa `superadmin` — sin cambios.

---

## 5. Orden de ejecución y migración de datos

1. Seeders (permisos + roles) — idempotentes, se re-ejecutan sin romper.
2. Rutas + Gate::before + controladores.
3. `php artisan db:seed --class=PermissionSeeder && --class=RoleSeeder` en local y prod.
4. Reasignar permisos al rol `admin` existente (grant-all corre en el seeder).
5. Frontend: menú + gating.
6. Verificación: probar cada rol preset contra un endpoint de cada módulo.

---

## 6. Riesgos

- **Prod ya está live** (memoria GCP). Los seeders deben ser idempotentes (ya lo son con `firstOrCreate`) y hay que correrlos en el deploy.
- Cambiar `super-admin`→`superadmin`: si algún usuario en prod tuviera el rol `super-admin` (no debería, nunca se sembró), quedaría huérfano. Verificar antes: `Role::where('name','super-admin')->exists()`.
- Las rutas que hoy son `role:admin` pasan a `permission:*`: el rol `admin` debe tener TODOS los permisos sembrados antes del deploy, o perderá acceso. El grant-all del seeder lo cubre.
