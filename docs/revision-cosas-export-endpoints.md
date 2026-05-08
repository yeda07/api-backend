# Revision de cosas.md y export-endpoints.md

Fecha: 2026-05-08

## Resumen

`cosas.md` estaba parcialmente cubierto. Los pendientes marcados en este documento ya fueron implementados y quedaron resumidos en `docs/faltantes-cosas-export-implementados.md`.

`export-endpoints.md` tenia faltantes importantes. Los 6 endpoints nuevos ya fueron agregados.

## cosas.md

### Ya existe

| Punto | Estado | Donde esta |
|---|---|---|
| Permisos `reports.read`, `teams.read`, `segments.read` | Listo | `database/seeders/PermissionSeeder.php` |
| Pipeline con 4 stages | Listo | `database/seeders/OpportunityStageSeeder.php` crea `Leads`, `Contactado`, `Negociacion`, `Cerrador`; test en `tests/Feature/OpportunityStageSeederTest.php` |
| 500 en `/api/finance/dashboard` y `/api/tenant/opportunity-products` | Cubierto por test | `tests/Feature/BackendAuditIntegrationTest.php` |
| Tags con `entity_types: null` como `[]` | Cubierto por test | `tests/Feature/BackendAuditIntegrationTest.php` |
| Users con `role_uid`, `role_name`, `status` | Listo | `app/Models/User.php`, `app/Repositories/UserRepository.php`, test en `tests/Feature/BackendAuditIntegrationTest.php` |
| Roles con `users_count` | Listo | `app/Models/Role.php`, test en `tests/Feature/BackendAuditIntegrationTest.php` |
| Settings/Users con `is_active` en PUT | Listo | `app/Http/Controllers/Api/UserController.php`, `app/Repositories/UserRepository.php`, test en `tests/Feature/SettingsUsersBackendIntegrationTest.php` |
| `GET /api/opportunities` paginado | Listo | `app/Services/OpportunityService.php` usa `ApiIndex::paginateOrGet()` |
| `GET /api/commissions/assignments` paginado | Listo | `app/Services/CommissionService.php` usa `ApiIndex::paginateOrGet()` |

### Existe con diferencia de ruta

| Punto pedido | Estado real |
|---|---|
| `GET /api/tenant/localization` sin `settings.manage` | Existe como `GET /api/settings/localization` sin permiso `settings.manage`, en `routes/api.php` y `app/Http/Controllers/Api/SettingsController.php` |

### Faltaba, ya implementado

| Faltante | Archivo sugerido |
|---|---|
| Modulos top-level `tasks`, `expenses`, `purchases` en `auth/init` | `app/Services/PlatformInitService.php` |
| `GET /api/opportunities/board` con paginacion real por `page`/`per_page` | `app/Services/OpportunityService.php` |
| `DELETE /api/document-types/{uid}` | `routes/api.php`, `app/Http/Controllers/Api/DocumentTypeController.php`, `app/Services/DocumentTypeService.php`, `app/Repositories/DocumentTypeRepository.php` |

Nota: `GET /api/opportunities/board` acepta `search`, `page` y `per_page`; cuando pagina, devuelve `data.pagination`.

## export-endpoints.md

### Ya existe

| Endpoint | Estado | Donde esta |
|---|---|---|
| `POST /api/search/export` | Listo | `routes/api.php`, `app/Http/Controllers/Api/SearchController.php`, `app/Services/SearchService.php`, documentado en `README.md` y `public/openapi.yaml` |

### Exports relacionados que ya existen, pero no son el contrato nuevo

| Endpoint existente | Formato | Donde esta |
|---|---|---|
| `GET /api/inventory/report/export` | CSV | `routes/api.php`, `app/Http/Controllers/Api/InventoryController.php`, `app/Services/InventoryService.php` |
| `GET /api/quotations/{uid}/pdf` | PDF | `routes/api.php`, `app/Http/Controllers/Api/QuotationController.php`, `app/Services/QuotationPdfService.php` |
| `GET /api/commissions/history/pdf` | PDF | `routes/api.php`, `app/Http/Controllers/Api/CommissionController.php`, `app/Services/CommissionService.php` |
| `GET /api/documents/download/{uid}` | Archivo | `routes/api.php`, `app/Http/Controllers/Api/DocumentController.php` |
| `GET /api/admin/billing/export` | JSON/CSV | `routes/api.php`, `app/Http/Controllers/Api/AdminBillingController.php` |

### Endpoints solicitados agregados

| Endpoint solicitado | Estado |
|---|---|
| `POST /api/reports/sales/export` | Listo |
| `POST /api/reports/inventory/export` | Listo |
| `POST /api/contacts/export` | Listo |
| `POST /api/inventory/products/export` | Listo |
| `POST /api/inventory/stock/export` | Listo |
| `POST /api/sales/finance/invoices/export` | Listo. Tambien existe `POST /api/finance/invoices/export`. |

## Conclusion

Los faltantes principales de `cosas.md` y `export-endpoints.md` quedaron implementados. Ver detalle operativo en `docs/faltantes-cosas-export-implementados.md`.
