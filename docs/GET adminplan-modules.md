# GET /admin/plan-modules

Revision e implementacion para frontend.

## Estado general

No existia un endpoint para obtener el catalogo dinamico de modulos de plan. Lo mas cercano era:

`GET /api/plans`

Ese endpoint devuelve planes y sus `features.modules`, pero no el catalogo de feature flags SaaS que usa el formulario para crear/editar planes.

## Endpoint agregado

`GET /api/admin/plan-modules`

Permisos requeridos:

- Token de superadmin/plataforma.
- Permiso `plans.manage`.

## Respuesta

```json
{
  "success": true,
  "message": null,
  "data": [
    { "key": "ventas", "label": "Ventas" },
    { "key": "inventario", "label": "Inventario" },
    { "key": "rh", "label": "RH / Comisiones" },
    { "key": "reportes", "label": "Reportes" },
    { "key": "multi-currency", "label": "Multi-currency" },
    { "key": "api-publica", "label": "API Publica" }
  ],
  "meta": null,
  "errors": null
}
```

## Uso esperado

El frontend puede reemplazar su array estatico de modulos por:

`GET /api/admin/plan-modules`

Estos modulos son feature flags SaaS para definir que capacidades incluye un plan. No son permisos RBAC granulares.

Para permisos granulares sigue existiendo:

`GET /api/rbac/permissions`

Para permisos filtrados por modulos activos del plan de un tenant:

`GET /api/admin/tenants/{uid}/permissions?only_active_modules=true`

## Nota backend

El backend tambien reconoce estas claves al validar permisos por plan:

- `ventas`
- `inventario`
- `rh`
- `reportes`
- `multi-currency`
- `api-publica`

## Pruebas

Validado con:

`php artisan test --filter=SuperAdminManagementTest`
