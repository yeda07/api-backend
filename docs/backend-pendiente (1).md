# Backend Pendiente

Revisión del archivo recibido desde frontend. Los pendientes listados ya quedan cubiertos por backend.

---

## Inventory - Filtro por texto `search`

Estado: implementado.

Endpoints:

```http
GET /api/inventory/master?search=texto
GET /api/inventory/warehouses?search=texto
GET /api/inventory/movements?search=texto
```

Comportamiento:

- `GET /api/inventory/master`: busca por `name` o `sku` del producto.
- `GET /api/inventory/warehouses`: busca por `name` o `code` de la bodega.
- `GET /api/inventory/movements`: busca por `product.name`, `product.sku`, `reference_uid` o `comment`.

El frontend ya puede enviar `search` al backend y dejar de filtrar solo la página actual.

---

## Inventory - Filtro `is_active`

Estado: implementado.

Endpoint:

```http
GET /api/inventory/master?is_active=false
GET /api/inventory/master?is_active=true
```

También se soporta en:

```http
GET /api/inventory/products?is_active=false
GET /api/inventory/products?is_active=true
```

Comportamiento:

- `is_active=true`: devuelve productos activos.
- `is_active=false`: devuelve productos inactivos.

---

## Roles - Filtro `only_active_modules`

Estado: implementado.

Endpoint:

```http
GET /api/rbac/roles?only_active_modules=true
```

Comportamiento:

- Devuelve los roles del tenant.
- Cuando `only_active_modules=true`, cada rol trae en `permissions` solo permisos pertenecientes a los módulos activos del plan del tenant.
- Usa la misma lógica de `PlanPermissionService` que ya protege creación/edición de roles.

Notas:

- Si el tenant no tiene plan o el plan no define `features.modules`, se devuelven todos los permisos como antes.
- Esto limpia el formulario del frontend; no cambia la seguridad de ejecución de endpoints.

---

## Resumen para frontend

Ya pueden consumir:

```http
GET /api/inventory/master?search=camiseta&is_active=true
GET /api/inventory/warehouses?search=central
GET /api/inventory/movements?search=REF-001
GET /api/rbac/roles?only_active_modules=true
```

No quedan cambios pendientes del archivo original.
