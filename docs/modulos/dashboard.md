# Dashboard

## Estado actual

El backend tiene `GET /api/dashboard/core` con los bloques principales que el frontend necesita: resumen, desglose, totales, KPIs, tags, tareas vencidas, cotizaciones recientes, productos con stock bajo y ventas mensuales.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET | `/api/dashboard/core` | Implementado |
| GET | `/api/activities?per_page=10&paginate=false` | Implementado |

## Campos disponibles en `/api/dashboard/core`

- `summary.new_customers_today`
- `summary.overdue_tasks_today`
- `summary.tasks_supported`
- `breakdown.accounts_created_today`
- `breakdown.contacts_created_today`
- `breakdown.crm_entities_created_today`
- `breakdown.tasks_due_today`
- `totals.accounts`
- `totals.contacts`
- `totals.crm_entities`
- `totals.tags`
- `totals.tasks`
- `kpis.conversion_rate`
- `kpis.mrr`
- `kpis.at_risk_count`
- `top_tags[]`
- `overdue_tasks[]`
- `recent_quotations[]`
- `low_stock_products[]`
- `monthly_sales[]`

## Brechas detectadas contra el documento

1. El documento marcaba como pendientes `kpis`, `overdue_tasks`, `recent_quotations`, `low_stock_products` y `monthly_sales`, pero esos bloques ya existen en `DashboardService`.
2. El documento indica que el frontend consume actividades como `GET /api/activities?per_page=10&paginate=false`. El backend podia devolver todo el tenant con `scope=tenant`, pero sin ese flag aplicaba row-level security.
3. No existe una tabla especifica de metas mensuales de ventas para `monthly_sales.goal`; por eso se retorna `goal: null`, como permite el documento.

## Implementado para cerrar brechas

- `GET /api/activities?per_page=10&paginate=false` ahora devuelve actividades del tenant completo sin exigir `scope=tenant`.
- Se conserva compatibilidad con `scope=tenant`.
- Se mantiene paginacion normal con row-level security cuando no se envia `paginate=false`.

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Services/DashboardService.php`
- `app/Http/Controllers/Api/ActivityController.php`
- `app/Services/ActivityService.php`

## Pruebas

- `tests/Feature/DashboardCoreIntegrationTest.php`
