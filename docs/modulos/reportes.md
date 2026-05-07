# Reportes

## Documento revisado

- Fuente: `required-backend/reports.md`
- Modulo: Reports
- Objetivo frontend: consumir reportes unificados bajo `/api/reports/*` con `kpis`, `chart_data`, `table_data` y filtros.

## Que ya tenia el backend

- Existian rutas `GET /api/reports/sales` y `GET /api/reports/inventory`.
- `sales` reutilizaba el dashboard financiero y devolvia campos heredados como `monthly_sales`.
- `inventory` reutilizaba `InventoryService::report` y devolvia campos heredados como `rupture_risk`, `summary_by_category` y `critical_products`.
- Existian fuentes de datos suficientes para construir reportes:
  - Cotizaciones y lineas: `Quotation`, `QuotationItem`.
  - Inventario: `InventoryProduct`, `InventoryStock`, `InventoryMovement`, `Warehouse`, `InventoryCategory`.

## Que hacia falta segun el documento

- Unificar la respuesta de `/api/reports/sales` con:
  - `kpis`
  - `chart_data.series`
  - `chart_data.labels` o `chart_data.categories`
  - `table_data`
- Unificar la respuesta de `/api/reports/inventory` con la misma estructura y `most_critical` para `tab=risk`.
- Agregar `GET /api/reports/filters`.
- Usar el permiso `reports.read`.
- Soportar filtros frontend:
  - `tab`
  - `period`
  - `warehouse`
  - `category`
  - `start_date`
  - `end_date`

## Implementado

### Endpoints

| Metodo | Endpoint | Permiso | Estado |
| --- | --- | --- | --- |
| GET | `/api/reports/sales` | `reports.read` | Implementado |
| GET | `/api/reports/inventory` | `reports.read` | Implementado |
| GET | `/api/reports/filters` | `reports.read` | Implementado |

### Sales

`GET /api/reports/sales` acepta:

- `tab=status|products|distributors|vs`
- `period=Hoy|Esta semana|Este mes|Este trimestre|Personalizado`
- `warehouse`
- `category`
- `start_date` y `end_date` cuando `period=Personalizado`

Devuelve:

- `kpis`: total generadas, aprobadas, pendientes y rechazadas.
- `chart_data`: cambia segun el tab.
- `table_data`: filas con cliente, fecha, items, total, ejecutivo y `statusBadge`.
- `monthly_sales`: se mantiene por compatibilidad con consumidores anteriores.

### Inventory

`GET /api/reports/inventory` acepta:

- `tab=warehouse|risk|movements|category|b2b`
- Los mismos filtros de periodo, bodega y categoria.

Devuelve:

- `kpis`: productos, disponibles, stock bajo y sin stock.
- `chart_data`: distribucion por riesgo, movimientos, categoria, bodega o producto.
- `table_data`: productos o movimientos segun el tab.
- `most_critical`: solo se llena cuando `tab=risk`.
- Campos heredados de `InventoryService::report`: `rupture_risk`, `summary_by_category`, `critical_products`.

### Filters

`GET /api/reports/filters` devuelve:

- `warehouses`: `{ value, label }`
- `categories`: `{ value, label }`

## Archivos principales

- `app/Services/ReportService.php`
- `app/Http/Controllers/Api/ReportController.php`
- `routes/api.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `app/Services/PlatformInitService.php`
- `tests/Feature/ReportsBackendIntegrationTest.php`

## Pruebas

- `ReportsBackendIntegrationTest`
  - Valida contrato de `/api/reports/sales`.
  - Valida contrato de `/api/reports/inventory?tab=risk`.
  - Valida opciones de `/api/reports/filters`.

## Pendiente

- No queda pendiente bloqueante del documento.
- La logica de ventas usa cotizaciones como fuente; si el negocio decide que "sales" debe basarse en facturas pagadas o pedidos cerrados, se puede cambiar la fuente manteniendo el mismo contrato JSON.
