# Finanzas — rentabilidad por tour (gastos e ingresos)

Módulo de control de **rentabilidad por tour**: `ingresos − gastos = margen`.
Está **integrado** con el resto de la plataforma:

- Los **tours** existentes son los centros de beneficio (no se duplican).
- Los **ingresos** se derivan automáticamente de las **reservas confirmadas**
  (no hay captura manual): ingreso de un tour = Σ `total_price` de sus reservas
  con `status = confirmed`.
- Lo nuevo es: **gastos**, **categorías de gasto**, **guías** (usuarios con rol
  `guia`) y el **dashboard de rentabilidad**.

Todos los endpoints requieren rol `admin` o `super-admin`.

## Modelo de datos

### `expense_categories`
Categorías de gasto (CRUD). Semilla por defecto: Insumos, Gasolina, Salarios,
Carro, Comida, Entradas, Hospedaje, Parqueo, Mantenimiento.

| Campo | Tipo | Notas |
|-------|------|-------|
| `name` | string | |
| `slug` | string | único; se genera solo si se omite |
| `icon` | string | nombre de bootstrap-icons (p. ej. `bi-fuel-pump`) |
| `is_active` | bool | |
| `sort_order` | int | orden en la UI |

### `expenses`
Un gasto imputado (o no) a un tour.

| Campo | Tipo | Notas |
|-------|------|-------|
| `tour_id` | fk tours, **nullable** | NULL = gasto general (bucket "Gastos generales") |
| `expense_category_id` | fk expense_categories | requerido |
| `guide_id` | fk users (uuid), nullable | guía al que se atribuye |
| `user_id` | fk users (uuid), nullable | quién lo registró (auto) |
| `amount` | decimal | |
| `comment` | string, nullable | |
| `spent_at` | date | por defecto hoy |
| `currency_code` | string(3) | |
| recibo | media | colección `receipt` (una foto), vía MediaLibrary |

### Guías
No hay tabla nueva: un guía es un **usuario con el rol `guia`** (Spatie). Los
gastos referencian a un usuario con ese rol.

## Cálculo de rentabilidad (`ProfitabilityService`)

- **Ingreso por tour** = Σ `total_price` de reservas con `bookable_type = Tour`
  y `status = confirmed`, agrupadas por tour.
- **Gasto por tour** = Σ `amount` de gastos agrupados por `tour_id`
  (los de `tour_id = null` forman el bucket "Gastos generales").
- **Margen** = ingreso − gasto. **% en gastos** = `gasto / ingreso × 100`
  (o 100 % si hay gasto sin ingreso).
- **Etiqueta**: `rentable` si hay ingreso; `sin_ingresos` si no.
- El ranking va ordenado por margen, de mayor a menor.

## Endpoints

Todos bajo `/api/v1`, con rol `admin|super-admin`.

### Dashboard
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/finance/summary` | Totales + ranking por tour + datos de gráficos (ingresos vs gastos por tour, gastos por categoría) |

### Categorías de gasto (JSON:API)
| Método | Ruta |
|--------|------|
| GET | `/expense-categories` |
| POST | `/expense-categories` |
| PATCH | `/expense-categories/{expenseCategory}` |
| DELETE | `/expense-categories/{expenseCategory}` |

### Gastos (JSON:API)
| Método | Ruta | Notas |
|--------|------|-------|
| GET | `/expenses` | filtros: `tour_id`, `expense_category_id`, `guide_id`, `date_from`, `date_to` |
| POST | `/expenses` | `spent_at` opcional (hoy por defecto) |
| PATCH | `/expenses/{expense}` | |
| DELETE | `/expenses/{expense}` | |
| POST | `/expenses/{expense}/receipt` | multipart `image`; sube/reemplaza el recibo |

### Guías (JSON plano)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/guides` | usuarios con rol `guia` |
| POST | `/guides` | otorga el rol `guia` — body `{ data: { attributes: { user_id } } }` |
| DELETE | `/guides/{user}` | revoca el rol `guia` |

## Componentes

| Pieza | Ubicación |
|-------|-----------|
| Modelos | `app/Models/Expense.php`, `app/Models/ExpenseCategory.php` |
| Servicio | `app/Services/ProfitabilityService.php` |
| Controladores | `app/Http/Controllers/Api/Travel/{Expense,ExpenseCategory,Guide,Finance}Controller.php` |
| Semilla | `database/seeders/ExpenseCategorySeeder.php` (rol `guia` en `RoleSeeder`) |
| Tests | `tests/Feature/Travel/FinanceTest.php` |
