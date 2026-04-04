# CMS Laravel - Arquitectura del Sistema

## Visión General

Este CMS está diseñado siguiendo los principios de WordPress pero construido con Laravel 13, ofreciendo soporte completo para API REST y vistas Blade tradicionales.

## Estructura del Proyecto

```
├── app/
│   ├── CMS/                    # Core del CMS
│   │   ├── Contracts/          # Interfaces del sistema
│   │   ├── Facades/            # Facades para acceso simplificado
│   │   ├── Services/           # Servicios principales
│   │   └── Traits/             # Traits reutilizables
│   └── ...
├── Modules/                    # Módulos del CMS (nwidart/laravel-modules)
│   ├── Core/                   # Módulo principal del CMS
│   ├── Pages/                  # Gestión de páginas (GrapesJS)
│   ├── Blog/                   # Sistema de blog (WYSIWYG)
│   ├── Media/                  # Gestión de medios
│   ├── Menu/                   # Sistema de menús
│   ├── Settings/               # Configuraciones del sitio
│   └── Admin/                  # Panel de administración
├── themes/                     # Sistema de temas
│   ├── default/                # Tema por defecto
│   │   ├── views/
│   │   ├── assets/
│   │   ├── theme.json
│   │   └── vite.config.js
│   └── admin/                  # Tema del panel admin
└── config/
    ├── modules.php
    └── theme.php
```

## Componentes Principales

### 1. Sistema de Temas (Custom)

Sistema propio de temas compatible con Laravel 13:
- **Herencia de temas**: Soporte para temas padre/hijo (estilo WordPress)
- **View Finder personalizado**: Busca vistas primero en el tema activo
- **Assets Management**: Compilación de assets por tema con Vite
- **Middleware de tema**: Selección dinámica de tema por ruta

### 2. Sistema de Módulos (nwidart/laravel-modules)

Cada módulo es independiente y contiene:
- Controllers (API y Web)
- Models
- Migrations
- Views (compatibles con el sistema de temas)
- Routes (api.php y web.php)
- Config
- Tests

### 3. Módulos del CMS

#### Core Module
- Settings Service
- Options API (similar a WordPress)
- Hook System (actions/filters)
- Asset Registration

#### Pages Module
- Page Model con soporte para:
  - SEO metadata
  - Templates personalizados
  - Contenido visual (GrapesJS)
  - Versionado/revisiones
- GrapesJS Integration
- Page Templates System

#### Blog Module
- Post Model
- Categories (taxonomías jerárquicas)
- Tags (taxonomías planas)
- Comments (opcional)
- WYSIWYG Editor (TinyMCE/Quill)

#### Media Module
- Upload y gestión de archivos
- Procesamiento de imágenes
- Biblioteca de medios
- CDN Support

#### Menu Module
- Menu Builder visual
- Ubicaciones de menú (como WordPress)
- Items dinámicos
- Nested menus

#### Admin Module
- Dashboard
- CRUD generado automáticamente
- Charts y estadísticas
- Activity logs

### 4. Editores Visuales

#### GrapesJS (Pages)
- Editor drag & drop
- Bloques personalizados
- Templates predefinidos
- Responsive design
- Export HTML/CSS

#### WYSIWYG (Blog)
- TinyMCE o Quill.js
- Inserción de medios
- Shortcodes support
- Code highlighting

## API Design

Todas las rutas siguen JSON:API specification:

```
GET    /api/v1/pages
POST   /api/v1/pages
GET    /api/v1/pages/{id}
PATCH  /api/v1/pages/{id}
DELETE /api/v1/pages/{id}

GET    /api/v1/posts
GET    /api/v1/categories
GET    /api/v1/tags
GET    /api/v1/menus
GET    /api/v1/settings
```

## Base de Datos

### Tablas Principales

```sql
-- Páginas
pages (id, title, slug, content, template, status, author_id, parent_id, meta, published_at, timestamps, soft_deletes)

-- Posts
posts (id, title, slug, excerpt, content, status, author_id, featured_image, meta, published_at, timestamps, soft_deletes)

-- Categorías (Polimórficas)
categories (id, name, slug, description, parent_id, meta, timestamps)
categorizables (category_id, categorizable_id, categorizable_type)

-- Tags (Polimórficas)
tags (id, name, slug, timestamps)
taggables (tag_id, taggable_id, taggable_type)

-- Menús
menus (id, name, slug, location, timestamps)
menu_items (id, menu_id, title, url, type, target_id, target_type, parent_id, order, meta, timestamps)

-- Media
media (id, filename, path, mime_type, size, alt, caption, meta, timestamps)

-- Settings
settings (id, group, key, value, type, timestamps)

-- Temas
themes (id, name, slug, is_active, parent_theme, settings, timestamps)
```

## Flujo de Peticiones

### Web Request
```
Request → Middleware (Theme) → Controller → Service → Repository → Model
                ↓
         Theme View Finder → Blade View → Response
```

### API Request
```
Request → Auth Middleware → API Controller → Service → Repository → Model
                                    ↓
                           JSON:API Resource → Response
```

## Seguridad

- Autenticación: Laravel Passport (OAuth2)
- Autorización: Spatie Permissions
- CSRF Protection
- XSS Prevention
- Rate Limiting
- Input Validation

## Extensibilidad

### Hooks System
```php
// Actions
do_action('cms.post.created', $post);
do_action('cms.page.before_render', $page);

// Filters
$content = apply_filters('cms.content.render', $rawContent);
$title = apply_filters('cms.seo.title', $title);
```

### Custom Post Types (futuro)
```php
register_post_type('product', [
    'label' => 'Products',
    'supports' => ['title', 'content', 'thumbnail'],
]);
```

## Testing Strategy

- Unit Tests: Models, Services
- Feature Tests: API endpoints, Controllers
- Browser Tests: Admin panel (Dusk)
- Coverage mínimo: 80%

## Performance

- Query optimization con eager loading
- Redis caching
- Asset bundling con Vite
- Lazy loading de imágenes
- Database indexing
