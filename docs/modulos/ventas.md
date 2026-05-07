# Sales

## Documento revisado

- Fuente: `required-backend/sales.md`
- Modulo: Sales
- Alcance: oportunidades, cotizaciones, finanzas operativas, reglas de credito y moneda.

## Que ya tenia el backend

- Oportunidades:
  - `GET/POST/PUT/DELETE /api/opportunities/stages`
  - `GET /api/opportunities/board`
  - `GET /api/opportunities/summary`
  - `GET/POST/PUT/DELETE /api/opportunities`
- Cotizaciones:
  - `GET/POST /api/quotations`
  - `GET/PUT /api/quotations/{uid}`
  - `GET /api/quotations/{uid}/pdf`
  - `POST /api/quotations/{uid}/send`
  - `POST /api/quotations/{uid}/items`
  - `PUT/DELETE /api/quotations/items/{itemUid}`
  - Alias CPQ en `/api/quotes`
- Finanzas:
  - `GET /api/finance/dashboard`
  - `GET /api/finance/alerts`
  - `GET/POST /api/finance/invoices`
  - `GET/POST /api/finance/payments`
  - `POST /api/finance/sync-overdue`
  - `GET/PUT /api/finance/credit/{type}/{uid}`
  - `GET /api/finance/customer/{type}/{uid}/summary`
- Moneda:
  - `GET/POST /api/currency/rates`
  - `POST /api/currency/convert`

## Que hacia falta segun el documento

- Ajustar `GET /api/finance/dashboard` al contrato de `FinanceDashboardView`:
  - `stats.monthly_sales`
  - `stats.monthly_sales_growth_percent`
  - `stats.pending_invoices_count`
  - `stats.pending_invoices_amount`
  - `stats.overdue_portfolio`
  - `stats.overdue_clients_count`
  - `stats.average_margin_percent`
  - `stats.margin_target_percent`
  - `weekly_sales` como arreglo simple de montos
  - `recent_invoices[].client_name` y `recent_invoices[].amount`
- Agregar reglas globales de credito:
  - `GET /api/finance/credit/rules`
  - `PUT /api/finance/credit/rules`
- Agregar excepciones de credito por cliente:
  - `GET /api/finance/credit/exceptions`
  - `POST /api/finance/credit/exceptions`
  - `PUT /api/finance/credit/exceptions/{uid}`
- Aceptar aliases frontend en moneda:
  - `POST /api/currency/convert` con `from`, `to`, `amount`
  - respuesta con `result`, `rate`, `rate_date`
  - `GET /api/currency/rates` con `code`, `name`, `rate`, `last_update`, `status`

## Implementado

### Finance dashboard

`GET /api/finance/dashboard` mantiene campos heredados y ahora agrega el contrato del frontend:

- `stats`
- `weekly_sales`
- `weekly_sales_details`
- `recent_invoices`

### Credit rules

| Metodo | Endpoint | Permiso | Estado |
| --- | --- | --- | --- |
| GET | `/api/finance/credit/rules` | `finance.read` | Implementado |
| PUT | `/api/finance/credit/rules` | `finance.manage` | Implementado |

Las reglas se guardan por tenant en `credit_rules`.

### Credit exceptions

| Metodo | Endpoint | Permiso | Estado |
| --- | --- | --- | --- |
| GET | `/api/finance/credit/exceptions` | `finance.read` | Implementado |
| POST | `/api/finance/credit/exceptions` | `finance.manage` | Implementado |
| PUT | `/api/finance/credit/exceptions/{uid}` | `finance.manage` | Implementado |

Las excepciones usan `credit_profiles` para mantener compatibilidad con el bloqueo real de credito.

### Currency

`GET /api/currency/rates` ahora devuelve filas listas para `MultiCurrencyView`:

- `code`
- `name`
- `rate`
- `last_update`
- `status`

`POST /api/currency/convert` acepta `from/to` y conserva `from_currency/to_currency`.

## Archivos principales

- `app/Services/FinancialDashboardService.php`
- `app/Services/CreditService.php`
- `app/Services/CurrencyService.php`
- `app/Http/Controllers/Api/FinancialOperationsController.php`
- `app/Models/CreditRule.php`
- `database/migrations/2026_05_07_000006_create_credit_rules_table.php`
- `tests/Feature/SalesBackendIntegrationTest.php`

## Pendiente

- La decision de negocio sobre moneda base sigue abierta, como indica el documento. Por ahora las tasas siguen expresadas por defecto desde `USD`, y `settings/localization` conserva la moneda base del tenant.
- `FinanceCPQView` continua fuera de alcance porque el documento no define endpoints nuevos dedicados para esa vista.
