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

## Roadmap Fase 1 MVP

### Objetivo

Lanzar una primera version cobrable centrada en:

- CRM comercial
- cotizador `CPQ`
- inventario comercial simple
- comisiones MVP
- conexion minima con contabilidad externa

El backend de Fase 1 prioriza flujo comercial utilizable y cobro temprano, sin reconstruir un ERP completo.

### Alcance final MVP

Entra en Fase 1:

- CRM comercial base
  - cuentas
  - contactos
  - actividades
  - interacciones
  - pipeline de oportunidades con etapas configurables
- CPQ y Price Books
  - listas de precios `B2B` y `B2C`
  - cotizaciones con items
  - descuentos
  - margen minimo viable
  - PDF descargable
  - envio de cotizacion por correo
- stock simple
  - maestro de inventario
  - stock fisico, reservado y disponible
  - multi-bodega simple
  - reservas desde cotizacion
  - movimientos entre bodegas
  - reporte comercial base
- comisiones MVP
  - reglas simples por producto o tipo de cliente
  - calculo sobre recaudos o facturas pagadas
  - resumen por vendedor
- finanzas operativas minimas
  - importacion de registros financieros externos
  - resumen por cliente
  - dashboard financiero basico

### Hitos

Hito 1. Seguridad y base comercial

- IAM/RBAC
- Row-Level Security
- cuentas
- contactos
- entidades CRM
- actividades e interacciones

Hito 2. Inventario comercial simple

- productos
- categorias
- bodegas
- existencias
- reservas
- movimientos
- reporte comercial base

Hito 3. CPQ MVP

- cotizaciones
- items
- listas de precios
- descuentos
- margen minimo
- reserva de stock desde cotizacion
- PDF descargable y enviable

Hito 4. Pipeline y visibilidad comercial

- etapas configurables
- oportunidades
- board
- resumen del pipeline

Hito 5. Finanzas operativas y comisiones

- importacion de facturas y recaudos
- resumen financiero por cliente
- dashboard financiero
- reglas de comision
- calculo de comisiones
- resumen por vendedor

### Dependencias

- `CRM base` depende de IAM/RBAC y Row-Level Security.
- `pipeline` depende de cuentas, contactos o entidades CRM visibles.
- `CPQ` depende de productos, listas de precios y entidades comerciales.
- `reserva de stock` depende de inventario + cotizacion.
- `comisiones` depende de cotizacion cerrada y de registros financieros importados.
- `finanzas operativas` depende de contrato de importacion externa, no de un ERP interno completo.

### Primer corte cobrable

El primer corte cobrable de Fase 1 queda definido cuando el backend permite:

- registrar y segmentar clientes
- crear oportunidades
- cotizar con listas de precios
- calcular descuentos y margen
- reservar stock critico
- generar y enviar cotizacion en PDF
- importar estado financiero basico del cliente
- calcular comisiones del vendedor

Ese corte ya habilita operacion comercial cobrable sin requerir contabilidad nativa completa.

### Que entra y que queda fuera

Entra en MVP:

- CRM comercial base
- pipeline simple
- cotizador con PDF
- stock comercial simple
- comisiones basicas
- integracion financiera minima por importacion

Queda fuera para fases posteriores:

- ERP contable completo
- aprobaciones multinivel de cotizaciones
- pricing avanzado por reglas complejas
- compras, lotes, series o valuacion contable de inventario
- nomina o liquidacion completa de comisiones
- automatizaciones comerciales avanzadas
- integracion cerrada con un proveedor contable especifico

### Estado actual Fase 1

- [x] CRM comercial base
- [x] pipeline de oportunidades
- [x] CPQ y Price Books MVP
- [x] PDF descargable y enviable de cotizaciones
- [x] inventario comercial simple
- [x] reservas de stock desde cotizacion
- [x] comisiones MVP
- [x] finanzas operativas minimas
- [ ] integracion dedicada con proveedor contable especifico

Conclusion:

- el backend funcional del MVP esta implementado
- la conexion con contabilidad externa existe como capa minima de importacion y consulta
- una integracion dedicada con un proveedor concreto queda fuera de este corte

## Roadmap Fase 2 y 3

### Objetivo

Extender el producto despues del MVP con dos modulos naturales del backend:

- Fase 2: `Gastos y Compras`
- Fase 3: `Proyectos`

Estas fases deben construirse como extension del nucleo comercial, financiero e inventario ya existente, sin convertir el sistema en un ERP completo desde el inicio.

### Dependencias entre Fases

#### Fase 1 -> Fase 2

`Gastos y Compras` depende de que Fase 1 ya tenga estables:

- clientes y entidades comerciales
- cotizaciones e invoices
- financial records
- inventory products y warehouses
- price books y costos base por producto

Dependencias clave:

- `compras` necesita productos, bodegas e inventario para registrar entradas o abastecimiento
- `gastos` necesita cuentas, CRM entities o centros de costo simples para imputacion
- `rentabilidad real` necesita ingresos de `invoices/payments` y costos/gastos asociados

#### Fase 1 -> Fase 3

`Proyectos` depende de que Fase 1 ya tenga estables:

- CRM comercial
- oportunidades
- cotizaciones
- facturacion
- comisiones

Dependencias clave:

- un proyecto debe poder vincularse a `account`, `contact`, `crm_entity`, `opportunity`, `quotation` o `invoice`
- el control de horas depende de usuarios, equipos y jerarquia ya existente
- la comision futura por ejecucion o entrega depende de ventas, facturacion y recaudo ya cerrados

#### Fase 2 -> Fase 3

`Proyectos` puede consumir datos de `Gastos y Compras` para medir rentabilidad por proyecto:

- compras asociadas a proyecto
- gastos operativos asociados a proyecto
- costo real vs ingreso facturado

### Decisiones de diseño no negociables

Para no bloquear Fase 2 y 3, el backend debe mantener estas decisiones:

- todas las entidades nuevas deben seguir contrato publico por `uid`
- toda entidad financiera o de costos debe ser `tenant-aware`
- los modelos nuevos deben respetar RBAC y RLS cuando aplique
- las referencias entre modulos deben preferir relaciones polimorficas o claves explicitas por `*_uid`
- no acoplar compras, gastos o proyectos a una contabilidad general completa
- mantener `financial_records` como capa operativa integrable y no como libro contable total
- conservar soporte multimoneda a nivel de cotizacion, factura, compra y gasto
- los eventos que impacten rentabilidad deben quedar trazables por documento fuente

### Roadmap de alto nivel

#### Secuencia sugerida

1. estabilizar Fase 1
2. construir Fase 2 `Gastos y Compras`
3. conectar rentabilidad por cliente/proyecto
4. construir Fase 3 `Proyectos`
5. conectar horas, hitos y facturacion de proyectos

#### Hitos Fase 2

Hito 2.1. Gastos operativos base

- `expense_categories`
- `expenses`
- asociacion opcional a cliente, proyecto o centro de costo
- estados basicos: `draft`, `submitted`, `approved`, `paid`

Hito 2.2. Compras base

- `purchase_orders`
- `purchase_order_items`
- asociacion a proveedor simple
- asociacion opcional a venta, oportunidad o proyecto

Hito 2.3. Impacto en rentabilidad

- reportes de ingreso vs gasto
- margen real por cliente
- margen real por proyecto
- consolidado de compras vinculadas a ventas

#### Hitos Fase 3

Hito 3.1. Proyectos base

- `projects`
- vinculacion a oportunidad, cotizacion o factura
- estados basicos: `planned`, `active`, `on_hold`, `completed`, `cancelled`

Hito 3.2. Planificacion

- `project_milestones`
- `project_tasks`
- dependencias simples entre tareas
- soporte backend para vista tipo Gantt

Hito 3.3. Horas y ejecucion

- `time_entries`
- horas por usuario, tarea y proyecto
- resumen de horas consumidas vs presupuestadas

Hito 3.4. Integracion financiera

- facturacion por proyecto
- costo acumulado por proyecto
- rentabilidad por proyecto
- insumos para comisiones futuras cuando aplique

### Riesgos principales

- definir mal el acoplamiento entre compras y stock puede obligar a rehacer inventario
- mezclar gastos operativos con contabilidad formal puede inflar demasiado Fase 2
- diseñar proyectos sin vinculo claro a ventas/facturas rompe el caso de negocio
- si no se modela bien multimoneda en gastos y compras, la rentabilidad real sera inconsistente
- si horas y tareas nacen sin jerarquia clara, el Gantt despues sera costoso de corregir

### Supuestos clave

- el producto seguira priorizando operacion comercial y servicios, no ERP full
- el volumen inicial de compras, gastos y proyectos sera moderado
- se aceptan reportes operativos primero y contabilidad avanzada despues
- la integracion contable externa seguira siendo complementaria, no reemplazada por libro mayor interno

### Alcance funcional Fase 2 - Gastos y Compras

Entra en Fase 2:

- registro de gastos operativos
- categorias de gasto
- asociacion a cliente
- asociacion a proyecto
- asociacion a centro de costo simple
- ordenes de compra basicas
- items de compra
- estado basico de compras
- comparativos ingreso vs gasto por cliente/proyecto

Se pospone:

- contabilidad general completa
- cuentas por pagar avanzadas
- conciliacion bancaria
- recepcion avanzada con lotes/series
- impuestos complejos
- proveedores con workflow completo ERP

### Alcance funcional Fase 3 - Proyectos

Entra en Fase 3:

- creacion y gestion de proyectos
- vinculacion a oportunidad, cotizacion o factura
- hitos principales
- tareas principales
- datos backend para Gantt
- control de horas por usuario
- resumen de avance y consumo
- rentabilidad basica del proyecto

Se pospone:

- PMO avanzada
- ruta critica avanzada
- capacidades tipo ERP/PSA enterprise
- asignacion compleja de recursos
- planeacion financiera profunda de proyectos

### Criterios de integracion

`Gastos y Compras` debe integrarse con:

- inventario para entradas o abastecimiento cuando aplique
- finanzas operativas para impacto en rentabilidad
- CRM para asociacion a cliente o negocio

`Proyectos` debe integrarse con:

- CRM para contexto comercial
- oportunidades para origen del proyecto
- facturacion para monetizacion
- comisiones cuando la politica comercial dependa de entrega o ejecucion

### Estado roadmap

- [x] Fase 1 definida e implementada a nivel backend
- [x] dependencias Fase 1 -> Fase 2 -> Fase 3 documentadas
- [x] roadmap de alto nivel documentado
- [x] alcance funcional de `Gastos y Compras` definido
- [x] alcance funcional de `Proyectos` definido
- [ ] implementacion backend de Fase 2 iniciada
- [ ] implementacion backend de Fase 3 iniciada

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

### `GET /api/accounts/{uid}/products`

Auth requerida.
Permiso: `products.read`.

Lista los productos o servicios instalados en la cuenta.

### `POST /api/accounts/{uid}/products`

Auth requerida.
Permiso: `products.install`.

Payload:

```json
{
  "product_uid": "e4db1242-ae51-4fa2-b5c7-14d7f42f5611",
  "product_version_uid": "f5c81f64-1f7a-4d58-a7e2-e7a560706af2",
  "installed_at": "2026-04-15",
  "status": "active",
  "notes": "Instalacion inicial"
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

## Catalogo de Productos y Servicios Complejos

El backend soporta un catalogo comercial independiente del inventario fisico para modelar:

- productos `product`
- servicios `service`
- versiones por producto
- dependencias entre productos
- productos instalados por cliente

Puede enlazarse opcionalmente a `inventory_products` cuando el item tambien requiere stock.

### `GET /api/products`

Auth requerida.
Permiso: `products.read`.

Filtros opcionales:

- `type`
- `status`

### `POST /api/products`

Auth requerida.
Permiso: `products.manage`.

Payload:

```json
{
  "name": "Suite ERP Base",
  "type": "product",
  "sku": "CAT-ERP-BASE",
  "description": "Modulo base",
  "status": "active",
  "initial_version": "1.0",
  "initial_release_date": "2026-04-01"
}
```

### `GET /api/products/{uid}`

Auth requerida.
Permiso: `products.read`.

### `PUT /api/products/{uid}`

Auth requerida.
Permiso: `products.manage`.

### `DELETE /api/products/{uid}`

Auth requerida.
Permiso: `products.manage`.

### `GET /api/products/{uid}/versions`

Auth requerida.
Permiso: `products.read`.

### `POST /api/products/{uid}/versions`

Auth requerida.
Permiso: `products.manage`.

Payload:

```json
{
  "version": "2.0",
  "release_date": "2026-05-01",
  "status": "active",
  "notes": "Release mayor"
}
```

### `PUT /api/products/versions/{versionUid}`

Auth requerida.
Permiso: `products.manage`.

### `GET /api/products/{uid}/dependencies`

Auth requerida.
Permiso: `products.read`.

### `POST /api/products/{uid}/dependencies`

Auth requerida.
Permiso: `products.manage`.

Payload:

```json
{
  "depends_on_product_uid": "06de5270-6412-44d0-a6ff-9d8a9d7f2b75",
  "dependency_type": "required",
  "message": "Debe incluir soporte"
}
```

### `DELETE /api/products/dependencies/{dependencyUid}`

Auth requerida.
Permiso: `products.manage`.

### Integracion con cotizaciones

`POST /api/quotations/{uid}/items` ahora tambien acepta:

```json
{
  "catalog_product_uid": "e4db1242-ae51-4fa2-b5c7-14d7f42f5611",
  "description": "Suite ERP Base",
  "quantity": 1,
  "unit_price": 1000
}
```

Reglas:

- agrega dependencias `required` automaticamente
- sugiere `optional` a nivel de servicio
- bloquea `incompatible` al aprobar la cotizacion
- si el producto esta enlazado a inventario, reutiliza el flujo de stock existente

### Checklist Catalogo Complejo

- [x] productos y servicios
- [x] versiones por producto
- [x] dependencias `required`, `optional`, `incompatible`
- [x] validacion de duplicados
- [x] validacion anti-ciclos
- [x] productos instalados por cuenta
- [x] integracion con cotizaciones
- [x] autoagregado de dependencias requeridas
- [x] bloqueo de incompatibilidades al aprobar cotizacion

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

### `GET /api/inventory/availability`

Auth requerida.
Permiso: `inventory.read`.

Query params opcionales:

- `product_uid`
- `warehouse_uid`

Devuelve disponibilidad consolidada usando la regla:

`stock_disponible = stock_fisico - stock_reservado`

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "filters": {
      "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
      "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713"
    },
    "data": [
      {
        "uid": "8d4fdb08-a5e5-429e-baa5-5c775ca6ef7f",
        "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
        "warehouse_uid": "4d0f1a13-c2a7-41be-9a4f-6db09b95f713",
        "physical_stock": 10,
        "reserved_stock": 5,
        "available_stock": 5,
        "stock_state": "normal",
        "stock_indicator": "green"
      }
    ],
    "summary": {
      "physical_stock": 10,
      "reserved_stock": 5,
      "available_stock": 5
    }
  },
  "errors": null
}
```

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

Notas:

- si se intenta reservar de nuevo el mismo `product_uid + warehouse_uid + source_type + source_uid`, el backend no duplica sin control
- en su lugar, consolida la cantidad en la reserva activa existente

### `POST /api/inventory/reservations/{uid}/consume`

Auth requerida.
Permiso: `inventory.reserve`.

Usa una reserva activa en una venta confirmada:

- descuenta stock fisico
- descuenta stock reservado
- cambia el estado de la reserva a `consumed`

Payload opcional:

```json
{
  "reference_type": "sale",
  "reference_uid": "sale-001",
  "comment": "Venta confirmada"
}
```

Success:

```json
{
  "success": true,
  "message": "Reserva consumida",
  "data": {
    "reservation": {
      "uid": "7a9865f2-e6ab-4d4f-a2e7-7d30b30a78d0",
      "status": "consumed"
    },
    "preview": {
      "stock_actual": 10,
      "stock_reservado_actual": 5,
      "stock_disponible": 5,
      "projected_physical_stock": 5,
      "projected_reserved_stock": 0,
      "projected_available_stock": 5
    }
  },
  "errors": null
}
```

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

### `GET /api/inventory/movements`

Auth requerida.
Permiso: `inventory.read`.

Query params opcionales:

- `product_uid`
- `warehouse_uid`
- `reference_type`
- `reference_uid`
- `type`

Permite consultar el historico de movimientos:

- entradas
- salidas
- transferencias
- reservas
- liberaciones
- consumos de reserva

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

Notas:

- si una cotizacion pasa a estado `approved`, el backend intenta reservar automaticamente el stock pendiente de sus items
- si un item aprobado no tiene `product_uid` o `warehouse_uid`, la aprobacion responde con error de validacion

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

### `GET /api/quotations/{uid}/pdf`

Auth requerida.
Permiso: `quotations.read`.

Devuelve la cotizacion en PDF descargable.

### `POST /api/quotations/{uid}/send`

Auth requerida.
Permiso: `quotations.update`.

Payload opcional:

```json
{
  "recipient_email": "compras@cliente.com",
  "subject": "Cotizacion comercial",
  "message": "Adjunto envio la cotizacion para revision."
}
```

Notas:

- si no se envia `recipient_email`, el backend intenta resolverlo desde la entidad comercial asociada
- si la cotizacion estaba en `draft`, al enviarla cambia a `sent`

## Finanzas Operativas

### Price Books y Multimoneda

Las listas de precios soportan canales:

- `B2C`
- `B2B`
- `B2G`

Cada item de lista de precios puede incluir:

- `unit_price`
- `currency`
- `min_margin_percent`

Las cotizaciones soportan:

- `currency`
- `exchange_rate`
- `local_currency`

Esto permite cotizar en moneda extranjera y facturar en moneda local.

### Dashboard Financiero F1

### `GET /api/finance/dashboard`

Auth requerida.
Permiso: `finance.read`.

Devuelve el resumen financiero operativo del tenant e incluye:

- `monthly_sales`
- `pending_invoices`
- `overdue_invoices`
- `average_margin`
- `weekly_sales`
- `recent_invoices`

Tambien conserva las claves historicas `totals` y `counts` para no romper integraciones anteriores.

### Cotizaciones F1

Ademas de `/api/quotations`, el backend expone aliases funcionales bajo `/api/quotes`.

### `GET /api/quotes`

Auth requerida.
Permiso: `quotations.read`.

Lista cotizaciones del tenant.

### `POST /api/quotes`

Auth requerida.
Permiso: `quotations.create`.

Permite crear cotizaciones con validacion de stock, precio y credito.

Payload ejemplo:

```json
{
  "entity_type": "account",
  "entity_uid": "8adf7bb4-5f3f-4d15-84ea-4d6c0bdbdb0a",
  "price_book_uid": "b1b8a3d3-1f1a-49e7-9bdf-9d4b83d406d2",
  "currency": "USD",
  "local_currency": "COP",
  "exchange_rate": 4000
}
```

### `POST /api/quotes/{uid}/items`

Auth requerida.
Permiso: `quotations.update`.

Agrega items y recalcula precios, descuentos, totales y margen.

### `POST /api/quotes/{uid}/approve`

Auth requerida.
Permiso: `quotations.update`.

Aprueba la cotizacion y dispara la logica de reserva de stock cuando aplica.

### `POST /api/quotes/{uid}/reject`

Auth requerida.
Permiso: `quotations.update`.

Marca la cotizacion como rechazada.

### `POST /api/quotes/{uid}/convert`

Auth requerida.
Permiso: `finance.manage`.

Convierte la cotizacion a factura.

### `POST /api/price-books`

Auth requerida.
Permiso: `price-books.manage`.

Payload ejemplo:

```json
{
  "name": "Lista B2G USD",
  "key": "lista-b2g-usd",
  "channel": "B2G",
  "items": [
    {
      "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
      "unit_price": 100,
      "currency": "USD",
      "min_margin_percent": 10
    }
  ]
}
```

### `POST /api/finance/invoices`

Auth requerida.
Permiso: `finance.manage`.

Genera factura a partir de una cotizacion.

Payload:

```json
{
  "quotation_uid": "8adf7bb4-5f3f-4d15-84ea-4d6c0bdbdb0a",
  "invoice_number": "FAC-0001",
  "currency": "COP",
  "exchange_rate": 4000,
  "due_date": "2026-05-08"
}
```

Reglas:

- valida riesgo de credito del cliente antes de facturar
- exige stock reservado suficiente para todos los items
- consume reservas de inventario al confirmar la factura
- crea/actualiza cartera operativa en `financial_records`

Estados soportados:

- `draft`
- `issued`
- `partial`
- `paid`
- `overdue`

### `GET /api/finance/invoices`

Auth requerida.
Permiso: `finance.read`.

Query params opcionales:

- `entity_type`
- `entity_uid`
- `status`

Permite listar facturas por cliente y estado.

### `POST /api/finance/payments`

Auth requerida.
Permiso: `finance.manage`.

Registra pagos parciales o totales.

Payload:

```json
{
  "invoice_uid": "8adf7bb4-5f3f-4d15-84ea-4d6c0bdbdb0a",
  "amount": 200000,
  "payment_date": "2026-04-08",
  "method": "transfer",
  "external_reference": "PAY-0001"
}
```

Reglas:

- no permite pagos mayores al saldo pendiente
- actualiza `paid_total`
- actualiza `outstanding_total`
- cambia la factura a `partial` o `paid`
- registra el movimiento en cartera operativa

### `POST /api/payments`

Auth requerida.
Permiso: `finance.manage`.

Alias funcional de `POST /api/finance/payments`.

### `GET /api/finance/payments`

Auth requerida.
Permiso: `finance.read`.

Query params opcionales:

- `invoice_uid`

Devuelve historial de pagos.

### `GET /api/payments/{invoiceUid}`

Auth requerida.
Permiso: `finance.read`.

Devuelve historial de pagos asociado a una factura puntual.

### `GET /api/finance/credit/{type}/{uid}`

Auth requerida.
Permiso: `finance.read`.

Devuelve resumen de credito del cliente:

- `credit_limit`
- `status`
- `outstanding_total`
- `overdue_total`
- `has_overdue`
- `max_days_overdue`
- `auto_block`

### `PUT /api/finance/credit/{type}/{uid}`

Auth requerida.
Permiso: `finance.manage`.

Payload:

```json
{
  "credit_limit": 5000,
  "status": "ok",
  "max_days_overdue": 30,
  "auto_block": true
}
```

Estados soportados:

- `ok`
- `blocked`

### Tasas de Cambio

### `GET /api/currency/rates`

Auth requerida.
Permiso: `finance.read`.

Lista tasas de cambio registradas.

### `POST /api/currency/rates`

Auth requerida.
Permiso: `finance.manage`.

Registra o actualiza tasas de cambio.

Payload:

```json
{
  "from_currency": "USD",
  "to_currency": "COP",
  "rate": 4000,
  "date": "2026-04-08"
}
```

### `POST /api/currency/convert`

Auth requerida.
Permiso: `finance.read`.

Convierte montos entre monedas usando la tasa registrada.

Payload:

```json
{
  "amount": 100,
  "from_currency": "USD",
  "to_currency": "COP",
  "date": "2026-04-08"
}
```

### `GET /api/finance/alerts`

Auth requerida.
Permiso: `finance.read`.

Devuelve alertas financieras dedicadas:

- facturas vencidas
- clientes en riesgo
- total vencido

Success:

```json
{
  "success": true,
  "message": null,
  "data": {
    "summary": {
      "overdue_invoices_count": 1,
      "customers_at_risk_count": 1,
      "overdue_total": 1000
    },
    "overdue_invoices": [
      {
        "record_uid": "f1c8f0d1-5d5b-4c73-9f0b-7cb3d95e6024",
        "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
        "external_reference": "FAC-0001",
        "currency": "COP",
        "outstanding_amount": 1000,
        "due_at": "2026-04-07"
      }
    ],
    "customer_risk": [
      {
        "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
        "entity_type": "App\\Models\\Account",
        "outstanding_amount": 1000,
        "overdue_amount": 1000,
        "risk_level": "high"
      }
    ]
  },
  "errors": null
}
```

### `POST /api/finance/sync-overdue`

Auth requerida.
Permiso: `finance.manage`.

Sincroniza facturas emitidas o parciales cuyo `due_date` ya vencio y las pasa a `overdue`.

Tambien actualiza la cartera operativa enlazada a esas facturas.

### `GET /api/finance/records`

Auth requerida.
Permiso: `finance.read`.

Permite consultar cartera operativa consolidada.

Query params opcionales:

- `entity_type`
- `entity_uid`
- `status`
- `record_type`
- `source_system`

### `POST /api/finance/import`

Auth requerida.
Permiso: `finance.manage`.

Permite importar registros financieros externos para integracion contable minima.

### `GET /api/finance/customer/{type}/{uid}/summary`

Auth requerida.
Permiso: `finance.read`.

Devuelve resumen de cartera por cliente:

- `invoiced`
- `paid`
- `outstanding`
- `overdue`

### `GET /api/finance/dashboard`

Auth requerida.
Permiso: `finance.read`.

Devuelve resumen operativo financiero del tenant.

### Bloqueo de Credito

El backend bloquea operaciones comerciales si:

- el perfil de credito esta en `blocked`
- el cliente tiene deuda vencida
- el saldo supera el limite de credito definido

El bloqueo se valida en:

- creacion y actualizacion operativa de cotizaciones
- facturacion desde cotizacion

### Checklist Finanzas Operativas

- [x] precios por canal `B2C`, `B2B`, `B2G`
- [x] cotizaciones con descuentos y margen
- [x] aliases F1 en `/api/quotes`
- [x] cotizaciones en moneda extranjera
- [x] facturacion en moneda local con tasa de cambio
- [x] tasas de cambio y conversion de moneda
- [x] facturas desde cotizacion
- [x] pagos parciales y totales
- [x] aliases F1 en `/api/payments`
- [x] saldo pendiente por factura
- [x] estado de cartera por cliente
- [x] bloqueo por riesgo de credito
- [x] reglas finas de credito con `max_days_overdue` y `auto_block`
- [x] alertas de morosidad y riesgo
- [x] sincronizacion formal de facturas `overdue`
- [x] integracion con inventario al facturar
- [x] dashboard financiero F1

## Comisiones

### Planes y Asignaciones

El backend soporta planes de comision por:

- `sale`
- `margin`
- `target`

Cada plan puede incluir:

- `base_percent`
- `tiers_json`
- `starts_at`
- `ends_at`
- `active`
- `role_uids`

### `GET /api/commissions/plans`

Auth requerida.
Permiso: `commissions.read`.

Lista planes de comision del tenant.

### `POST /api/commissions/plans`

Auth requerida.
Permiso: `commissions.manage`.

Payload:

```json
{
  "name": "Plan Escalonado",
  "type": "sale",
  "base_percent": 5,
  "tiers_json": [
    {
      "threshold": 1000,
      "percent": 7
    }
  ],
  "active": true
}
```

### `PUT /api/commissions/plans/{uid}`

Auth requerida.
Permiso: `commissions.manage`.

Actualiza plan, vigencia, roles aplicables o tramos.

### `GET /api/commissions/assignments`

Auth requerida.
Permiso: `commissions.read`.

Filtros opcionales:

- `user_uid`
- `manager_uid`
- `active`

### `POST /api/commissions/assignments`

Auth requerida.
Permiso: `commissions.manage`.

Payload:

```json
{
  "user_uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
  "commission_plan_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb",
  "starts_at": "2026-04-01",
  "ends_at": "2026-04-30",
  "active": true
}
```

Reglas:

- valida solapamientos de asignaciones activas
- valida que el vendedor cumpla los roles aplicables del plan cuando existan

### `PUT /api/commissions/assignments/{uid}`

Auth requerida.
Permiso: `commissions.manage`.

Permite ajustar plan, fechas o estado de la asignacion.

### Metas

### `GET /api/commissions/targets`

Auth requerida.
Permiso: `commissions.read`.

Filtro opcional:

- `user_uid`

### `POST /api/commissions/targets`

Auth requerida.
Permiso: `commissions.manage`.

Payload:

```json
{
  "user_uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
  "period": "2026-04",
  "target_amount": 2000
}
```

### Dashboard y Simulacion

### `GET /api/commissions/dashboard/{userUid}`

Auth requerida.
Permiso: `commissions.read`.

Devuelve:

- `monthly_target`
- `sales_achieved`
- `projected_commission`
- `liquidated_commission`
- `progress_percent`
- `active_assignment`

### `POST /api/commissions/simulate`

Auth requerida.
Permiso: `commissions.read`.

Payload:

```json
{
  "user_uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
  "sale_amount": 1500,
  "margin_amount": 300,
  "period": "2026-04"
}
```

Devuelve:

- porcentaje aplicado
- comision proyectada
- desglose por tramo en `tier_breakdown`

### Entradas y Liquidaciones

Las entradas de comision siguen generandose desde recaudos pagados. Si el vendedor tiene un plan activo, el backend prioriza ese plan; si no, hace fallback a las reglas historicas de `commission_rules`.

### `GET /api/commissions/entries`

Auth requerida.
Permiso: `commissions.read`.

Filtro opcional:

- `user_uid`

### `POST /api/commissions/financial-records`

Auth requerida.
Permiso: `commissions.manage`.

Registra el recaudo y genera `commission_entries`.

### `PUT /api/commissions/entries/{uid}/pay`

Auth requerida.
Permiso: `commissions.manage`.

Marca una entrada puntual como pagada.

### `GET /api/commissions/runs`

Auth requerida.
Permiso: `commissions.read`.

Filtros opcionales:

- `user_uid`
- `status`

### `POST /api/commissions/runs`

Auth requerida.
Permiso: `commissions.manage`.

Payload:

```json
{
  "user_uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
  "period_start": "2026-04-01",
  "period_end": "2026-04-30"
}
```

Crea una liquidacion a partir de entradas no liquidadas del periodo.

### `POST /api/commissions/runs/{uid}/approve`

Auth requerida.
Permiso: `commissions.manage`.

Marca la liquidacion como `approved`.

### `POST /api/commissions/runs/{uid}/pay`

Auth requerida.
Permiso: `commissions.manage`.

Marca la liquidacion como `paid` y sincroniza sus entradas asociadas.

### Checklist Comisiones

- [x] planes de comision
- [x] tramos escalonados
- [x] asignacion de planes a vendedores
- [x] metas por periodo
- [x] simulador backend
- [x] dashboard por vendedor
- [x] entradas de comision desde recaudos
- [x] historial de comisiones
- [x] liquidaciones con estados `pending`, `approved`, `paid`
- [x] compatibilidad con reglas historicas

## Gastos y Compras

### Categorias y Proveedores

El backend soporta categorias de gasto y proveedores simples para Fase 2.

### `GET /api/expenses/categories`

Auth requerida.
Permiso: `expenses.read`.

### `POST /api/expenses/categories`

Auth requerida.
Permiso: `expenses.manage`.

Payload:

```json
{
  "name": "Viaticos",
  "key": "viaticos",
  "description": "Gastos de desplazamiento"
}
```

### `PUT /api/expenses/categories/{uid}`

Auth requerida.
Permiso: `expenses.manage`.

### `DELETE /api/expenses/categories/{uid}`

Auth requerida.
Permiso: `expenses.manage`.

### `GET /api/expenses/suppliers`

Auth requerida.
Permiso: `expenses.read`.

### `POST /api/expenses/suppliers`

Auth requerida.
Permiso: `expenses.manage`.

Payload:

```json
{
  "name": "Proveedor Industrial",
  "email": "proveedor@example.com",
  "phone": "3001234567"
}
```

### Gastos

Los gastos pueden asociarse opcionalmente a:

- cliente
- entidad CRM
- centro de costo simple
- proveedor

### `GET /api/expenses`

Auth requerida.
Permiso: `expenses.read`.

Filtros opcionales:

- `category_uid`
- `supplier_uid`
- `entity_type`
- `entity_uid`
- `status`
- `cost_center`

### `POST /api/expenses`

Auth requerida.
Permiso: `expenses.manage`.

Payload:

```json
{
  "expense_category_uid": "8d7e7c3d-7f8b-4b3a-a2b0-e773f8ed0ed9",
  "supplier_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb",
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "cost_center": "ventas-b2b",
  "title": "Visita comercial",
  "amount": 250,
  "currency": "COP",
  "expense_date": "2026-04-09",
  "status": "approved"
}
```

Estados soportados:

- `draft`
- `submitted`
- `approved`
- `paid`

### `PUT /api/expenses/{uid}`

Auth requerida.
Permiso: `expenses.manage`.

### `DELETE /api/expenses/{uid}`

Auth requerida.
Permiso: `expenses.manage`.

### Ordenes de Compra

Las ordenes de compra soportan:

- proveedor
- items
- costo unitario
- asociacion opcional a `source_type/source_uid`
- recepcion con impacto en inventario cuando el item trae `product_uid` y `warehouse_uid`

### `GET /api/purchases/orders`

Auth requerida.
Permiso: `purchases.read`.

Filtros opcionales:

- `status`
- `supplier_uid`
- `source_type`
- `source_uid`

### `POST /api/purchases/orders`

Auth requerida.
Permiso: `purchases.manage`.

Payload:

```json
{
  "supplier_uid": "4c0d9cf6-8a27-4db2-b95d-4a6711ec92bb",
  "purchase_number": "OC-0001",
  "currency": "COP",
  "items": [
    {
      "product_uid": "0c6f2739-f6f4-4cc8-9f86-6726bb4d4d5b",
      "warehouse_uid": "43e01e15-bdd3-49b9-918e-700a526caa17",
      "description": "Router Empresarial",
      "quantity": 5,
      "unit_cost": 80
    }
  ]
}
```

### `GET /api/purchases/orders/{uid}`

Auth requerida.
Permiso: `purchases.read`.

### `PUT /api/purchases/orders/{uid}`

Auth requerida.
Permiso: `purchases.manage`.

Las lineas solo se reescriben libremente mientras la orden sigue en `draft`.

### `POST /api/purchases/orders/{uid}/approve`

Auth requerida.
Permiso: `purchases.manage`.

Marca la orden como `approved`.

### `POST /api/purchases/orders/{uid}/receive`

Auth requerida.
Permiso: `purchases.manage`.

Marca la orden como `received` y aumenta stock fisico cuando los items tienen producto y bodega.

Estados soportados:

- `draft`
- `approved`
- `received`
- `cancelled`

### Rentabilidad basica

### `GET /api/expenses/report`

Auth requerida.
Permiso: `expenses.report`.

Permite comparar ingreso vs gasto con filtros opcionales:

- `entity_type`
- `entity_uid`
- `cost_center`

Devuelve:

- `income_total`
- `expense_total`
- `real_margin`
- desglose de gastos por categoria

### Checklist Gastos y Compras

- [x] categorias de gasto
- [x] proveedores simples
- [x] gastos asociados a cliente o centro de costo
- [x] estados basicos de gasto
- [x] ordenes de compra
- [x] items de compra
- [x] aprobacion de compras
- [x] recepcion de compras con impacto en inventario
- [x] reporte simple de rentabilidad ingreso vs gasto
- [x] permisos y pruebas del modulo

## Integridad multi-tenant y base de datos

Los ultimos ajustes estructurales del backend dejaron:

- `custom_field_values` con FK real hacia `tenants` y `custom_fields`
- `system_logs` amarrado a `tenant_id`
- eliminacion de la tabla legacy `duplicate_logs`
- relacion `tenant()` estandarizada en el nucleo de modelos multi-tenant

Para reiniciar base de datos local y reaplicar todo:

```bash
php artisan migrate:fresh --force
```

## Checklist CORE - Inventario Comercial

- [x] Vista maestro de inventario con filtros por categoria, bodega y estado
- [x] Stock fisico, reservado y disponible
- [x] Indicador de stock `green`, `yellow`, `red`
- [x] Consulta de disponibilidad por producto y bodega
- [x] Reserva de stock integrada a cotizacion B2B real
- [x] Preview de reserva con resultado proyectado
- [x] Alerta `422` cuando la reserva excede disponible
- [x] Control de duplicidad de reservas por referencia
- [x] Consumo de reservas en venta confirmada
- [x] Multi-bodega con movimiento y vista previa
- [x] Historico de movimientos de inventario
- [x] Reporte comercial con resumen por categoria
- [x] Productos criticos
- [x] Export CSV
- [x] Widget backend de riesgo de ruptura
- [x] Auto-reserva al aprobar cotizaciones
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

## Gestion Documental B2G

El backend extiende la boveda existente de `documents` para soportar documentos legales por cliente con versionado, alertas y validacion documental.

Rutas principales:

- `GET /api/document-types`
- `POST /api/document-types`
- `PUT /api/document-types/{uid}`
- `POST /api/documents`
- `PUT /api/documents/{uid}`
- `GET /api/documents/{uid}`
- `GET /api/documents/{uid}/versions`
- `GET /api/documents/account/{accountUid}`
- `GET /api/documents/missing/{accountUid}`
- `GET /api/document-alerts`
- `POST /api/document-alerts/generate`
- `POST /api/document-alerts/{uid}/read`

Capacidades:

- tipos de documento por tenant (`RUT`, camara de comercio, polizas, certificados)
- control de vigencia con estados `valid`, `expiring`, `expired`
- versionado de reemplazos sin perder historial
- alertas configurables por tipo y dias antes del vencimiento
- validacion de documentos requeridos por cuenta
- bloqueo de cotizaciones y facturacion cuando el tenant define documentos obligatorios y faltan para la cuenta

Payload base de carga:

```json
{
  "entity_type": "account",
  "entity_uid": "c3c2f54c-e8d0-4056-a4ce-ef6550ca4a61",
  "document_type_uid": "d1a8c0f2-8f8c-4d32-b2d0-6c2ebc9a0011",
  "issue_date": "2026-04-20",
  "expiration_date": "2026-07-20",
  "file": "contrato.pdf"
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
