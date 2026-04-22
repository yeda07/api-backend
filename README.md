# API Backend

Backend Laravel 13 multi-tenant para CRM, CPQ, inventario comercial, finanzas operativas, documentos, compras, comisiones y partners.

## Documentacion

- Swagger UI: `/swagger/index.html`
- OpenAPI YAML: `/openapi.yaml`
- Base path de la API: `/api`

La especificacion `public/openapi.yaml` es la fuente recomendada para integraciones.

## Requisitos

- PHP `8.3+`
- Composer `2+`
- PostgreSQL recomendado para produccion
- Redis recomendado para cache y colas en produccion

## Instalacion local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan serve
```

## Variables importantes

### Aplicacion

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_KEY`

### Base de datos

- `DB_CONNECTION`
- `DATABASE_URL`

### Cache y colas

- `REDIS_CLIENT=phpredis`
- `REDIS_URL`
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `DASHBOARD_CACHE_STORE=redis`

### Archivos y documentos

- `FILESYSTEM_DISK=local`
- `DOCUMENTS_DISK=documents`
- `DOCUMENTS_ROOT=/app/storage/app/documents`

### Logging

- `LOG_CHANNEL=stderr`
- `LOG_LEVEL=info`

### Rendimiento

- `API_FORCE_INDEX_PAGINATION=true`
- `API_DEFAULT_PER_PAGE=25`
- `API_MAX_PER_PAGE=100`
- `API_RATE_LIMIT_PER_MINUTE=240`
- `API_GUEST_RATE_LIMIT_PER_MINUTE=60`

## Despliegue en Render

El proyecto esta preparado para desplegarse por `Dockerfile`.

Checklist minima:

1. Configurar `APP_ENV=production` y `APP_DEBUG=false`.
2. Configurar `APP_URL` con la URL publica real.
3. Conectar PostgreSQL por `DATABASE_URL`.
4. Conectar Redis por `REDIS_URL`.
5. Mantener `CACHE_STORE=redis` y `QUEUE_CONNECTION=redis`.
6. Verificar `healthCheckPath: /up`.
7. Ejecutar migraciones con `php artisan migrate --force`.

## Seguridad

La API usa Sanctum con tokens Bearer.

Header esperado:

```http
Authorization: Bearer {token}
Accept: application/json
```

Capas activas:

- autenticacion por token
- validacion de tenant activo
- validacion de acceso del token al tenant
- `full access` para rutas privadas
- permisos por endpoint
- Row-Level Security sobre recursos comerciales

## Contrato de respuesta

Respuesta exitosa:

```json
{
  "success": true,
  "message": null,
  "data": {},
  "errors": null
}
```

Error de validacion:

```json
{
  "success": false,
  "message": "Validation error",
  "data": null,
  "errors": {
    "field_name": [
      "Mensaje de error"
    ]
  }
}
```

Si una respuesta indexada esta paginada, puede incluir:

```json
{
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 25,
      "total": 120,
      "last_page": 5
    }
  }
}
```

## Regla de identificadores

La API expone `uid` como identificador publico.

- no se deben consumir `id` internos
- los payloads publicos usan `*_uid`
- las relaciones externas del contrato estan orientadas a `uid`

## Modulos principales

- `Auth`: login, logout, 2FA, recuperacion de contrasena, perfil autenticado
- `RBAC`: usuarios, roles, permisos, jerarquia, acceso efectivo
- `CRM`: cuentas, contactos, relaciones, entidades CRM, tags
- `Operacion comercial`: tareas, actividades, interacciones, busqueda, dashboard
- `Catalogo y CPQ`: productos, versiones, dependencias, price books, quotations, quotes
- `Inventario`: maestro, categorias, productos, bodegas, reservas, movimientos, reportes
- `Finanzas`: records, credit profile, invoices, payments, dashboard, currency
- `Comisiones`: planes, asignaciones, metas, entradas, liquidaciones, simulador
- `Documentos`: tipos, documentos, versiones, alertas, faltantes
- `Gastos y compras`: categorias, proveedores, centros de costo, expenses, purchase orders
- `Partners`: partners, opportunities, resources
- `Observabilidad`: metrics y logs

## Endpoints clave

- `POST /api/login`
- `GET /api/me`
- `GET /api/accounts`
- `GET /api/contacts`
- `GET /api/quotations`
- `GET /api/products`
- `GET /api/inventory/master`
- `GET /api/finance/dashboard`
- `GET /api/commissions/dashboard/{userUid}`
- `GET /api/document-alerts`

La lista completa, con metodos, permisos y parametros de ruta, esta en Swagger.

## Notas de rendimiento

El backend ya incluye:

- throttling global de API
- paginacion configurable para endpoints indexados
- cache de dashboard configurable
- soporte Redis para cache y colas
- endurecimiento del logger para que fallos de escritura no rompan requests
- disco de documentos desacoplado del disco general

## Comandos utiles

```bash
php artisan route:list
php artisan migrate --force
php artisan optimize:clear
php artisan config:clear
php artisan queue:listen
```
