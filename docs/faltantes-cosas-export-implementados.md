# Faltantes implementados de cosas.md y export-endpoints.md

Fecha: 2026-05-08

## Implementado de cosas.md

### 1. Modulos top-level en `auth/init`

Se agregaron los modulos principales:

- `tasks`
- `expenses`
- `purchases`

Donde:

- `app/Services/PlatformInitService.php`

Validacion:

- `tests/Feature/MissingBackendItemsIntegrationTest.php`

### 2. Paginacion real en board de oportunidades

`GET /api/opportunities/board` ahora acepta:

- `page`
- `per_page`
- `search`

La respuesta mantiene `data.stages` y agrega `data.pagination` cuando aplica paginacion.

Donde:

- `app/Services/OpportunityService.php`

Validacion:

- `tests/Feature/MissingBackendItemsIntegrationTest.php`

### 3. DELETE de tipos documentales

Nuevo endpoint:

```http
DELETE /api/document-types/{uid}
```

Permiso:

```txt
documents.manage
```

Regla:

- si el tipo documental tiene documentos asociados, responde `422`
- si no tiene documentos, lo elimina

Donde:

- `routes/api.php`
- `app/Http/Controllers/Api/DocumentTypeController.php`
- `app/Services/DocumentTypeService.php`
- `app/Repositories/DocumentTypeRepository.php`

Validacion:

- `tests/Feature/MissingBackendItemsIntegrationTest.php`

## Implementado de export-endpoints.md

Todos reciben:

```json
{
  "format": "excel | pdf | csv",
  "fields": ["campo_opcional"],
  "filters": {}
}
```

Notas:

- `csv` devuelve `text/csv; charset=UTF-8`
- `pdf` devuelve `application/pdf`
- `excel` devuelve MIME de Excel y extension `.xlsx`
- `fields` ignora campos inexistentes
- todos devuelven `Content-Disposition: attachment`

### Endpoints agregados

| Endpoint | Permiso | Donde |
|---|---|---|
| `POST /api/reports/sales/export` | `reports.read` | `ReportController`, `ReportService` |
| `POST /api/reports/inventory/export` | `reports.read` | `ReportController`, `ReportService` |
| `POST /api/contacts/export` | `contacts.read` | `ContactController`, `ContactService` |
| `POST /api/inventory/products/export` | `inventory.read` | `InventoryProductController`, `InventoryService` |
| `POST /api/inventory/stock/export` | `inventory.read` | `InventoryController`, `InventoryService` |
| `POST /api/sales/finance/invoices/export` | `finance.read` | `FinancialOperationsController`, `InvoiceService` |

Tambien se agrego alias backend consistente:

```http
POST /api/finance/invoices/export
```

## Soporte comun de exportacion

Se agrego:

- `app/Services/ExportService.php`

Este servicio centraliza:

- generacion CSV
- generacion PDF simple
- filtro de columnas por `fields`
- nombres de archivo
- headers de descarga

## Verificacion

Prueba puntual:

```bash
php artisan test tests\Feature\MissingBackendItemsIntegrationTest.php
```

Resultado:

```txt
4 passed, 28 assertions
```

Suite completa:

```bash
php artisan test
```

Resultado:

```txt
69 passed, 670 assertions
```
