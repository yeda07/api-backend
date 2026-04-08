# API Backend

Backend Laravel 13 con Sanctum para un CRM multi-tenant.

## Contrato de respuesta

Todas las respuestas JSON siguen este formato:

```json
{
  "success": true,
  "message": null,
  "data": {},
  "errors": null
}
```

Errores de validacion:

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

## Identificadores publicos

La API no expone `id` como identificador publico.

- Todas las rutas y respuestas publicas usan `uid`.
- Los campos internos como `id`, `tenant_id`, `account_id`, `from_id`, `to_id`, `custom_field_id` y similares quedan solo para base de datos.
- Los payloads legacy con `*_id` no hacen parte del contrato publico.

## Base URL

Prefijo API: `/api`

## Autenticacion

La API usa `Bearer Token` con Sanctum.

Header:

```http
Authorization: Bearer {token}
Accept: application/json
```

## Endpoints

### `POST /api/login`

Payload:

```json
{
  "email": "admin@empresa.com",
  "password": "secret123"
}
```

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "token": "plain-text-token",
    "user": {
      "uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
      "name": "Admin",
      "email": "admin@empresa.com",
      "tenant_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb"
    }
  },
  "errors": null
}
```

Error credenciales:

```json
{
  "success": false,
  "message": "Credenciales incorrectas",
  "data": null,
  "errors": {
    "credentials": [
      "Credenciales incorrectas"
    ]
  }
}
```

Error tenant inactivo o vencido:

```json
{
  "success": false,
  "message": "Cuenta suspendida o vencida",
  "data": null,
  "errors": {
    "tenant": [
      "Cuenta suspendida o vencida"
    ]
  }
}
```

### `GET /api/me`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
    "name": "Admin",
    "email": "admin@empresa.com",
    "tenant_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb",
    "manager_uid": null
  },
  "errors": null
}
```

### `POST /api/logout`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": "Sesion cerrada correctamente",
  "data": null,
  "errors": null
}
```

### `GET /api/users`

Auth requerida.
Permiso: `users.manage`.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
      "name": "Admin",
      "email": "admin@empresa.com",
      "tenant_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb",
      "manager_uid": null
    }
  ],
  "errors": null
}
```

### `GET /api/rbac/roles`

Auth requerida.
Permiso: `users.manage`.

Devuelve los roles disponibles del tenant con sus permisos asociados.

### `POST /api/rbac/roles`

Auth requerida.
Permiso: `users.manage`.

Payload:

```json
{
  "name": "Analista",
  "key": "analyst",
  "description": "Rol personalizado",
  "permission_uids": [
    "8c1d8f80-6f6a-4216-a67a-d7d0e6f06651"
  ]
}
```

Notas:

- crea roles personalizados por tenant
- los roles del sistema no se crean por este endpoint

### `PUT /api/rbac/roles/{roleUid}`

Auth requerida.
Permiso: `users.manage`.

Permite actualizar:

- `name`
- `key`
- `description`
- `permission_uids`

Notas:

- los roles del sistema no se pueden editar

### `DELETE /api/rbac/roles/{roleUid}`

Auth requerida.
Permiso: `users.manage`.

Notas:

- los roles del sistema no se pueden eliminar

### `GET /api/rbac/permissions`

Auth requerida.
Permiso: `users.manage`.

Devuelve el catalogo global de permisos.

### `GET /api/users/{uid}/access`

Auth requerida.
Permiso: `users.manage`.

Devuelve:

- `user`
- `roles`
- `direct_permissions`
- `effective_permissions`

### `POST /api/users/{uid}/roles`

Auth requerida.
Permiso: `users.manage`.

Payload:

```json
{
  "role_uid": "0dd0f4fc-2292-470e-b063-bd95f46d5a6f"
}
```

### `DELETE /api/users/{uid}/roles/{roleUid}`

Auth requerida.
Permiso: `users.manage`.

Retira el rol del usuario.

### `POST /api/users/{uid}/permissions`

Auth requerida.
Permiso: `users.manage`.

Payload:

```json
{
  "permission_uid": "8c1d8f80-6f6a-4216-a67a-d7d0e6f06651"
}
```

### `DELETE /api/users/{uid}/permissions/{permissionUid}`

Auth requerida.
Permiso: `users.manage`.

Retira un permiso directo del usuario.

### `POST /api/users/{uid}/manager`

Auth requerida.
Permiso: `users.manage`.

Payload:

```json
{
  "manager_uid": "25e2375d-55de-4458-a15c-2a424565f20e"
}
```

Notas:

- Si `manager_uid` es `null`, se desasigna la jerarquia.
- Esta relacion se usa para Row-Level Security de cartera.

### `GET /api/plans`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "df6b9f59-f371-4851-a9fa-18570f6d8310",
      "name": "Pro",
      "price": "49.99",
      "max_users": 10,
      "max_records": null,
      "max_accounts": 1000,
      "max_contacts": 5000,
      "max_entities": 1000
    }
  ],
  "errors": null
}
```

### `POST /api/plans`

Auth requerida.

Payload:

```json
{
  "name": "Pro",
  "price": 49.99,
  "max_users": 10,
  "max_accounts": 1000,
  "max_contacts": 5000,
  "max_entities": 1000
}
```

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "uid": "df6b9f59-f371-4851-a9fa-18570f6d8310",
    "name": "Pro",
    "price": "49.99",
    "max_users": 10,
    "max_records": null,
    "max_accounts": 1000,
    "max_contacts": 5000,
    "max_entities": 1000
  },
  "errors": null
}
```

### `GET /api/accounts`

Auth requerida.
Permiso: `accounts.read`.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
      "name": "Acme SAS",
      "document": "900123456",
      "email": "contacto@acme.com",
      "industry": "Manufactura",
      "website": "https://acme.com",
      "phone": "+57 3000000000",
      "address": "Bogota",
      "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
      "created_at": "2026-04-08",
      "updated_at": "2026-04-08"
    }
  ],
  "errors": null
}
```

### `GET /api/accounts/{uid}`

Auth requerida.

Mismo shape de una cuenta individual dentro de `data`.

### `POST /api/accounts`

Auth requerida.
Permiso: `accounts.create`.

Payload:

```json
{
  "name": "Acme SAS",
  "document": "900123456",
  "email": "contacto@acme.com",
  "industry": "Manufactura",
  "website": "https://acme.com",
  "phone": "+57 3000000000",
  "address": "Bogota"
}
```

### `PUT /api/accounts/{uid}`

Auth requerida.
Permiso: `accounts.update`.

Payload igual a create.

### `POST /api/accounts/{uid}/owner`

Auth requerida.
Permiso: `accounts.update`.

Payload:

```json
{
  "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e"
}
```

### `DELETE /api/accounts/{uid}`

Auth requerida.
Permiso: `accounts.delete`.

Success:

```json
{
  "success": true,
  "message": "Account deleted",
  "data": null,
  "errors": null
}
```

### `GET /api/contacts`

Auth requerida.
Permiso: `contacts.read`.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "31c5d086-88fe-4294-9ef3-c9c4941c3bb5",
      "first_name": "Ana",
      "last_name": "Gomez",
      "email": "ana@acme.com",
      "phone": "+57 3001111111",
      "position": "Gerente",
      "account_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
      "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
      "created_at": "2026-04-08",
      "updated_at": "2026-04-08"
    }
  ],
  "errors": null
}
```

### `GET /api/contacts/{uid}`

Auth requerida.

Mismo shape de un contacto individual dentro de `data`.

### `POST /api/contacts`

Auth requerida.
Permiso: `contacts.create`.

Payload:

```json
{
  "first_name": "Ana",
  "last_name": "Gomez",
  "email": "ana@acme.com",
  "phone": "+57 3001111111",
  "position": "Gerente",
  "account_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61"
}
```

Notas:

- `account_uid` es opcional.
- `account_id` no hace parte del contrato publico.

### `PUT /api/contacts/{uid}`

Auth requerida.
Permiso: `contacts.update`.

Payload igual a create.

### `POST /api/contacts/{uid}/owner`

Auth requerida.
Permiso: `contacts.update`.

Payload:

```json
{
  "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e"
}
```

### `DELETE /api/contacts/{uid}`

Auth requerida.
Permiso: `contacts.delete`.

Success:

```json
{
  "success": true,
  "message": "Contact deleted",
  "data": null,
  "errors": null
}
```

### `GET /api/relations`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "f0e7d7f5-6265-4bc8-ae26-fdb17977b6bc",
      "from_type": "App\\Models\\Contact",
      "to_type": "App\\Models\\Account",
      "relation_type": "works_for",
      "from_uid": "31c5d086-88fe-4294-9ef3-c9c4941c3bb5",
      "to_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
      "created_at": "2026-04-08",
      "updated_at": "2026-04-08"
    }
  ],
  "errors": null
}
```

### `GET /api/relations/with-entities`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "f0e7d7f5-6265-4bc8-ae26-fdb17977b6bc",
      "from_type": "Contact",
      "from": "Ana Gomez",
      "from_uid": "31c5d086-88fe-4294-9ef3-c9c4941c3bb5",
      "to_type": "Account",
      "to": "Acme SAS",
      "to_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
      "relation_type": "works_for"
    }
  ],
  "errors": null
}
```

### `GET /api/relations/{type}/{uid}`

Auth requerida.

Busca relaciones de una entidad.

Valores recomendados para `type`:

- `account`
- `contact`
- `crm-entity`

### `GET /api/relations/hierarchy/{type}/{uid}`

Auth requerida.

Devuelve jerarquia `reports_to` con `employee_uid` y `reports_to_uid`.

### `POST /api/relations`

Auth requerida.

Payload:

```json
{
  "from_type": "contact",
  "from_uid": "31c5d086-88fe-4294-9ef3-c9c4941c3bb5",
  "to_type": "account",
  "to_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "relation_type": "works_for"
}
```

Notas:

- `from_uid` y `to_uid` son obligatorios.
- `from_id` y `to_id` no hacen parte del contrato publico.

### `DELETE /api/relations/{uid}`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": "Relation deleted",
  "data": null,
  "errors": null
}
```

### `GET /api/crm-entities`

Auth requerida.
Permiso: `crm-entities.read`.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "4d793c95-c3f7-4548-90a3-74a5c8b6c6df",
      "type": "B2B",
      "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
      "profile_data": {
        "company_name": "Acme SAS",
        "document": "900123456",
        "email": "b2b@acme.com"
      },
      "created_at": "2026-04-08",
      "updated_at": "2026-04-08"
    }
  ],
  "errors": null
}
```

### `POST /api/crm-entities`

Auth requerida.
Permiso: `crm-entities.create`.

Payload B2B:

```json
{
  "type": "B2B",
  "profile_data": {
    "company_name": "Acme SAS",
    "document": "900123456",
    "email": "b2b@acme.com"
  }
}
```

Payload B2C:

```json
{
  "type": "B2C",
  "profile_data": {
    "first_name": "Ana",
    "last_name": "Gomez",
    "email": "ana@correo.com"
  }
}
```

Payload B2G:

```json
{
  "type": "B2G",
  "profile_data": {
    "institution_name": "Alcaldia",
    "department": "Compras"
  }
}
```

### `POST /api/crm-entities/{uid}/owner`

Auth requerida.
Permiso: `crm-entities.update`.

Payload:

```json
{
  "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e"
}
```

### `GET /api/tags`

Auth requerida.
Permiso: `tags.manage`.

Devuelve el catalogo de etiquetas del tenant.

### `POST /api/tags`

Auth requerida.
Permiso: `tags.manage`.

Payload:

```json
{
  "name": "VIP",
  "key": "vip",
  "color": "#FFD700",
  "category": "segment"
}
```

### `PUT /api/tags/{uid}`

Auth requerida.
Permiso: `tags.manage`.

Permite actualizar `name`, `key`, `color` y `category`.

### `DELETE /api/tags/{uid}`

Auth requerida.
Permiso: `tags.manage`.

### `POST /api/tags/assign`

Auth requerida.
Permiso: `tags.manage`.

Payload:

```json
{
  "tag_uid": "2901ff7f-7d9b-4424-82a4-e4cf8518b3a8",
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61"
}
```

### `POST /api/tags/unassign`

Auth requerida.
Permiso: `tags.manage`.

Mismo payload de asignacion.

### `POST /api/search`

Auth requerida.
Permiso: `search.use`.

Payload base:

```json
{
  "entity_types": ["accounts", "contacts", "crm-entities"],
  "query": "VIP",
  "tag_uids": ["2901ff7f-7d9b-4424-82a4-e4cf8518b3a8"],
  "created_from": "2026-04-01",
  "created_to": "2026-04-08",
  "owner_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
  "custom_field_filters": [
    {
      "custom_field_uid": "7b11ff7f-7d9b-4424-82a4-e4cf8518b3a8",
      "value": "Norte"
    }
  ],
  "sort_by": "created_at",
  "sort_direction": "desc",
  "page": 1,
  "per_page": 15
}
```

Notas:

- `entity_types` acepta `accounts`, `contacts` y `crm-entities`
- `sort_by` acepta `created_at`, `updated_at`, `name`, `email` y `type`
- `sort_direction` acepta `asc` y `desc`
- `custom_field_filters` permite filtrar por valor de campo personalizado en entidades compatibles
- la respuesta incluye `results`, `totals` y `meta` por tipo de entidad

### `POST /api/search/export`

Auth requerida.
Permiso: `search.use`.

Permite exportar el mismo segmento filtrado que usa `POST /api/search`.

Payload base:

```json
{
  "format": "json",
  "entity_types": ["accounts"],
  "tag_uids": ["2901ff7f-7d9b-4424-82a4-e4cf8518b3a8"],
  "sort_by": "created_at",
  "sort_direction": "desc"
}
```

Formatos soportados:

- `json`: devuelve envelope normal con `results`, `totals` y `filters`
- `csv`: devuelve descarga `text/csv`

Notas:

- respeta Row-Level Security y permisos igual que el endpoint de busqueda
- reutiliza filtros por etiquetas, fechas, responsable y campos personalizados

### `GET /api/dashboard/core`

Auth requerida.
Permiso: `dashboard.read`.

Devuelve metricas operativas basicas del tenant actual.

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "summary": {
      "new_customers_today": 3,
      "overdue_tasks_today": 0,
      "tasks_supported": true
    },
    "breakdown": {
      "accounts_created_today": 1,
      "contacts_created_today": 1,
      "crm_entities_created_today": 1,
      "tasks_due_today": 0
    },
    "totals": {
      "accounts": 10,
      "contacts": 24,
      "crm_entities": 6,
      "tags": 8,
      "tasks": 0
    },
    "top_tags": []
  },
  "errors": null
}
```

Notas:

- usa cache backend
- por defecto el dashboard puede usar un store dedicado definido en `DASHBOARD_CACHE_STORE`
- la recomendacion para produccion es `DASHBOARD_CACHE_STORE=redis`

## Historial, Productividad y Anexos

### `GET /api/interactions/{type}/{uid}`

Auth requerida.
Permiso: `interactions.read`.

Devuelve la linea de tiempo cronologica inversa de una entidad visible.

Valores recomendados para `type`:

- `account`
- `contact`
- `crm-entity`

### `POST /api/interactions/notes`

Auth requerida.
Permiso: `interactions.create`.

Payload:

```json
{
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "subject": "Nota inicial",
  "content": "Cliente interesado en propuesta",
  "meta": {
    "channel": "manual"
  },
  "occurred_at": "2026-04-08T10:00:00Z"
}
```

### `POST /api/interactions/calls`

Auth requerida.
Permiso: `interactions.create`.

Payload:

```json
{
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "subject": "Llamada de seguimiento",
  "content": "Se agendo reunion",
  "meta": {
    "duration_seconds": 180
  }
}
```

### `POST /api/interactions/emails`

Auth requerida.
Permiso: `interactions.create`.

Mismo contrato base de interacciones, con `type = email`.

Notas:

- el timeline es inmutable
- los cambios de estado de actividades se registran automaticamente como `status_change`

### `GET /api/activities`

Auth requerida.
Permiso: `activities.read`.

Devuelve actividades visibles del usuario autenticado.

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "3fa2e311-5d8f-4d91-8f2b-71bdf51ab9f0",
      "type": "meeting",
      "title": "Demo comercial",
      "description": "Presentacion al cliente",
      "status": "pending",
      "scheduled_at": "2026-04-10T15:00:00.000000Z",
      "assigned_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
      "entity_type": "account",
      "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
      "created_at": "2026-04-08T12:00:00.000000Z",
      "updated_at": "2026-04-08T12:00:00.000000Z"
    }
  ],
  "errors": null
}
```

### `GET /api/activities/range`

Auth requerida.
Permiso: `activities.read`.

Query params requeridos:

- `from`
- `to`

Ejemplo:

```http
GET /api/activities/range?from=2026-04-08&to=2026-04-15
```

Notas:

- antes de consultar, el backend sincroniza automaticamente actividades vencidas y cambia su estado a `overdue`

### `GET /api/activities/{uid}`

Auth requerida.
Permiso: `activities.read`.

Devuelve una actividad individual dentro de `data`.

### `POST /api/activities`

Auth requerida.
Permiso: `activities.create`.

Payload:

```json
{
  "type": "meeting",
  "title": "Demo comercial",
  "description": "Presentacion al cliente",
  "status": "pending",
  "scheduled_at": "2026-04-10T15:00:00Z",
  "assigned_user_uid": "25e2375d-55de-4458-a15c-2a424565f20e",
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61"
}
```

Estados soportados:

- `pending`
- `completed`
- `overdue`

Tipos soportados:

- `task`
- `reminder`
- `meeting`

### `PUT /api/activities/{uid}`

Auth requerida.
Permiso: `activities.update`.

Permite actualizar estado, agenda, descripcion y asignacion.

Notas:

- cuando cambia `status` y la actividad esta asociada a una entidad, el backend registra una interaccion automatica de tipo `status_change`

### `DELETE /api/activities/{uid}`

Auth requerida.
Permiso: `activities.delete`.

### `POST /api/documents`

Auth requerida.
Permiso: `documents.create`.

Payload multipart/form-data:

- `entity_type`
- `entity_uid`
- `file`

Restricciones:

- solo se permiten archivos PDF

Notas:

- el archivo queda almacenado en el disco configurado por `filesystems.default`
- cada documento queda vinculado a la entidad usando `entity_type` y `entity_uid`

### `GET /api/documents/entity/{type}/{uid}`

Auth requerida.
Permiso: `documents.read`.

Lista los documentos asociados a una entidad visible.

Valores recomendados para `type`:

- `account`
- `contact`
- `crm-entity`

### `GET /api/documents/download/{uid}`

Auth requerida.
Permiso: `documents.read`.

Devuelve el archivo PDF para descarga.

## Checklist CORE - Historial, Productividad y Anexos

- linea de tiempo inmutable de notas, llamadas y correos: completado
- auditoria automatica de cambios de estado en timeline: completado
- agenda de actividades con estados `pending`, `completed`, `overdue`: completado
- consulta de actividades por rango de fechas: completado
- boveda documental base con subida, listado y descarga de PDF: completado

## Inventario Comercial y CPQ

### `GET /api/inventory/master`

Auth requerida.
Permiso: `inventory.read`.

Query params opcionales:

- `category_uid`
- `warehouse_uid`
- `stock_state`

Valores soportados para `stock_state`:

- `normal`
- `low`
- `out`

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "filters": {
      "category_uid": "6f8ed2b4-6d53-4600-a090-f14e6741f851",
      "warehouse_uid": null,
      "stock_state": "normal"
    },
    "data": [
      {
        "uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
        "sku": "SKU-001",
        "product": "Router Empresarial",
        "category_uid": "6f8ed2b4-6d53-4600-a090-f14e6741f851",
        "category_name": "Hardware",
        "warehouse_uid": null,
        "stock_physical_total": 12,
        "stock_reserved_total": 3,
        "stock_available_total": 9,
        "stock_state": "normal",
        "stock_indicator": "green",
        "reorder_point": 5
      }
    ],
    "summary": {
      "products": 1,
      "total_physical_stock": 12,
      "total_reserved_stock": 3,
      "total_available_stock": 9
    }
  },
  "errors": null
}
```

Notas:

- la vista maestro ya entrega el equivalente backend de la tabla principal del inventario
- `stock_indicator` usa `green`, `yellow` y `red`

### `GET /api/inventory/categories`

Auth requerida.
Permiso: `inventory.read`.

### `POST /api/inventory/categories`

Auth requerida.
Permiso: `inventory.manage`.

Payload:

```json
{
  "name": "Hardware",
  "key": "hardware",
  "description": "Equipos y accesorios"
}
```

### `GET /api/inventory/products`

Auth requerida.
Permiso: `inventory.read`.

### `POST /api/inventory/products`

Auth requerida.
Permiso: `inventory.manage`.

Payload:

```json
{
  "category_uid": "6f8ed2b4-6d53-4600-a090-f14e6741f851",
  "sku": "SKU-001",
  "name": "Router Empresarial",
  "description": "Router para cliente B2B",
  "reorder_point": 5,
  "warehouse_stocks": [
    {
      "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
      "physical_stock": 8
    }
  ]
}
```

### `GET /api/inventory/warehouses`

Auth requerida.
Permiso: `inventory.read`.

### `POST /api/inventory/warehouses`

Auth requerida.
Permiso: `inventory.manage`.

Payload:

```json
{
  "name": "Bodega Principal",
  "code": "BOD-01",
  "location": "Bogota"
}
```

### `GET /api/inventory/warehouses/{uid}/stocks`

Auth requerida.
Permiso: `inventory.read`.

Devuelve la tabla de inventario filtrada para una bodega especifica.

### `POST /api/inventory/stocks/adjust`

Auth requerida.
Permiso: `inventory.manage`.

Payload:

```json
{
  "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
  "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
  "operation": "in",
  "quantity": 10,
  "comment": "Ingreso inicial"
}
```

Operaciones soportadas:

- `in`
- `out`
- `set`

### `POST /api/inventory/reservations`

Auth requerida.
Permiso: `inventory.reserve`.

Payload:

```json
{
  "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
  "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
  "quantity": 4,
  "source_type": "quotation_item",
  "source_uid": "64a31c76-1d53-4f2d-ae7d-253efad3fb0d",
  "comment": "Reserva comercial"
}
```

Success:

```json
{
  "success": true,
  "message": "Stock reservado",
  "data": {
    "reservation": {
      "uid": "7a9865f2-e6ab-4d4f-a2e7-7d30b30a78d0",
      "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
      "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
      "source_type": "quotation_item",
      "source_uid": "64a31c76-1d53-4f2d-ae7d-253efad3fb0d",
      "quantity": 4,
      "status": "active"
    },
    "preview": {
      "stock_actual": 12,
      "stock_reservado_actual": 7,
      "stock_disponible": 5,
      "unidades_a_reservar": 4,
      "resultado_final_proyectado": 5,
      "exceeds_available": false
    }
  },
  "errors": null
}
```

Notas:

- este preview es el contrato backend del modal de confirmacion de reserva
- si excede disponible, responde `422`

### `GET /api/inventory/reservations/source/{sourceType}/{sourceUid}`

Auth requerida.
Permiso: `inventory.read`.

Devuelve reservas agrupadas por origen comercial.

### `DELETE /api/inventory/reservations/{uid}`

Auth requerida.
Permiso: `inventory.reserve`.

Libera una reserva activa.

### `POST /api/inventory/movements/transfer`

Auth requerida.
Permiso: `inventory.manage`.

Payload:

```json
{
  "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
  "from_warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
  "to_warehouse_uid": "8638dc95-5690-4db0-b3d2-a6a3b8b95a15",
  "quantity": 4,
  "comment": "Rebalanceo"
}
```

Notas:

- devuelve `preview` con resultado proyectado en origen y destino

### `GET /api/inventory/report`

Auth requerida.
Permiso: `inventory.report`.

Devuelve:

- `summary_by_category`
- `critical_products`
- `rupture_risk`

### `GET /api/inventory/report/export`

Auth requerida.
Permiso: `inventory.report`.

Devuelve descarga `text/csv`.

## Cotizaciones B2B y Reserva desde CPQ

### `GET /api/quotations`

Auth requerida.
Permiso: `quotations.read`.

### `POST /api/quotations`

Auth requerida.
Permiso: `quotations.create`.

Payload:

```json
{
  "quote_number": "COT-0001",
  "title": "Cotizacion B2B Acme",
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "status": "draft",
  "currency": "COP"
}
```

### `GET /api/quotations/{uid}`

Auth requerida.
Permiso: `quotations.read`.

Devuelve la cotizacion con sus items y el indicador de reserva:

- `reservation_indicator = not_reserved`
- `reservation_indicator = partial`
- `reservation_indicator = reserved`

### `POST /api/quotations/{uid}/items`

Auth requerida.
Permiso: `quotations.update`.

Payload:

```json
{
  "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
  "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
  "description": "Servidor para proyecto B2B",
  "quantity": 5,
  "unit_price": 1500
}
```

### `POST /api/quotations/items/{itemUid}/reserve-stock`

Auth requerida.
Permiso: `inventory.reserve`.

Payload:

```json
{
  "quantity": 3,
  "comment": "Reserva desde CPQ"
}
```

Notas:

- usa el item de cotizacion como origen real de la reserva
- el backend devuelve el preview del modal de reserva y actualiza el indicador del item

### `DELETE /api/quotations/items/{itemUid}/reservations/{reservationUid}`

Auth requerida.
Permiso: `inventory.reserve`.

Libera una reserva hecha desde CPQ para ese item.

## Checklist CORE - Inventario Comercial

- [x] Vista maestro de inventario con filtros por categoria, bodega y estado
- [x] Stock fisico, reservado y disponible
- [x] Indicador de stock `green`, `yellow`, `red`
- [x] Reserva de stock integrada a cotizacion B2B real
- [x] Preview de reserva con resultado proyectado
- [x] Alerta `422` cuando la reserva excede disponible
- [x] Multi-bodega con movimiento y vista previa
- [x] Reporte comercial con resumen por categoria
- [x] Productos criticos
- [x] Export CSV
- [x] Widget backend de riesgo de ruptura
- [x] Tests de integracion del modulo

## Redis para Dashboard

Para dejar Redis como cache efectiva del dashboard:

```env
CACHE_STORE=database
DASHBOARD_CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

Notas:

- el dashboard intenta usar primero el store configurado en `DASHBOARD_CACHE_STORE`
- si Redis no esta disponible, hace fallback a `failover`
- esto permite usar Redis especificamente para analitica sin obligar a mover todo el cache global

## Rendimiento de Busqueda

El backend incluye un benchmark basico para medir escenarios representativos del motor de busqueda:

```bash
php artisan search:benchmark --iterations=5
```

Opciones:

- `--tenant_uid=` para fijar el tenant del benchmark
- `--user_uid=` para fijar el usuario autenticado del benchmark
- `--iterations=` para repetir escenarios y comparar tiempos promedio

El comando mide escenarios como:

- busqueda base multi-entidad
- busqueda con texto y rango de fechas
- busqueda ordenada sobre cuentas

El indice GIN para `crm_entities.profile_data` se crea automaticamente en PostgreSQL desde la migracion de tags.

### `GET /api/metrics/my-usage`

Auth requerida.

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "usage": {
      "accounts": 10,
      "contacts": 30,
      "entities": 2,
      "relations": 8
    },
    "limits": {
      "accounts": 1000,
      "contacts": 5000,
      "entities": 1000
    },
    "percentage": {
      "accounts": 1,
      "contacts": 0.6,
      "entities": 0.2
    },
    "alerts": {}
  },
  "errors": null
}
```

### `GET /api/logs`

Auth requerida.

Query params opcionales:

- `level`

Success:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "2fc2cfad-ac94-4435-88cf-3f89df7f5db5",
      "level": "info",
      "message": "Relacion creada",
      "context": {
        "data": {}
      },
      "created_at": "2026-04-08T03:00:00.000000Z",
      "updated_at": "2026-04-08T03:00:00.000000Z"
    }
  ],
  "errors": null
}
```

### `POST /api/custom-fields`

Auth requerida.

Payload:

```json
{
  "entity_type": "account",
  "name": "Region",
  "key": "region",
  "type": "select",
  "options": {
    "required": true,
    "values": ["Norte", "Centro", "Sur"]
  }
}
```

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "uid": "2901ff7f-7d9b-4424-82a4-e4cf8518b3a8",
    "entity_type": "App\\Models\\Account",
    "name": "Region",
    "key": "region",
    "type": "select",
    "options": {
      "required": true,
      "values": ["Norte", "Centro", "Sur"]
    },
    "created_at": "2026-04-08T03:00:00.000000Z",
    "updated_at": "2026-04-08T03:00:00.000000Z"
  },
  "errors": null
}
```

Notas:

- `entity_type` acepta aliases publicos como `account`, `contact` o `crm-entity`.

### `POST /api/custom-fields/value`

Auth requerida.

Payload:

```json
{
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "custom_field_uid": "2901ff7f-7d9b-4424-82a4-e4cf8518b3a8",
  "value": "Norte"
}
```

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "uid": "6ca4988e-f5ae-4991-a483-5d6d9b710643",
    "entity_type": "App\\Models\\Account",
    "value": "Norte",
    "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
    "custom_field_uid": "2901ff7f-7d9b-4424-82a4-e4cf8518b3a8",
    "created_at": "2026-04-08T03:00:00.000000Z",
    "updated_at": "2026-04-08T03:00:00.000000Z"
  },
  "errors": null
}
```

## Verificacion local

## Row-Level Security

La privacidad de cartera ya no depende solo de permisos de modulo.

- `owner` puede ver todo el tenant.
- un usuario normal ve sus propios registros.
- si tiene subordinados, tambien ve los registros asignados a su equipo.
- `accounts`, `contacts`, `crm-entities` y `relations` se filtran automaticamente.
- `contacts` puede heredar visibilidad desde la cuenta asociada si su responsable directo no esta definido.
- `relations` solo expone grafos entre entidades visibles.

Campos usados por RLS:

- `users.manager_id` / `manager_uid`
- `accounts.owner_user_id` / `owner_user_uid`
- `contacts.owner_user_id` / `owner_user_uid`
- `crm_entities.owner_user_id` / `owner_user_uid`

## Checklist IAM/RBAC Backend

- [x] Tokens Sanctum aislados por tenant
- [x] Bloqueo por intentos fallidos
- [x] Recuperacion de contrasena
- [x] 2FA obligatorio
- [x] Tablas de roles, permisos y pivotes
- [x] Middleware dinamico de permisos
- [x] Integracion de permisos en endpoints
- [x] Roles base `owner`, `manager` y `seller`
- [x] CRUD de roles personalizados
- [x] Asignacion y revocacion de roles a usuarios
- [x] Asignacion y revocacion de permisos directos
- [x] Jerarquia de equipos en backend
- [x] Asignacion de responsables de cartera
- [x] Row-Level Security en `accounts`
- [x] Row-Level Security en `contacts`
- [x] Row-Level Security en `relations`
- [x] Row-Level Security en `crm-entities`
- [x] Bloqueo por tenant inactivo o vencido
- [x] Tests de integracion para auth, RBAC y RLS

Comandos usados para validar la migracion a `uid` y el contrato uniforme de respuestas:

```bash
php artisan migrate --force
php artisan route:list
php artisan test
```
