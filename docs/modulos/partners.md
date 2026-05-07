# Partners

## Estado actual

El backend tiene partners, oportunidades de partners y recursos para partners. Las oportunidades viven bajo `/api/partners/opportunities`, y los recursos bajo `/api/partner-resources`.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET/POST | `/api/partners` | Implementado |
| PUT | `/api/partners/{uid}` | Implementado |
| GET/POST | `/api/partners/opportunities` | Implementado |
| GET | `/api/partners/opportunities/{uid}` | Implementado |
| POST | `/api/partners/opportunities/validate` | Implementado |
| POST | `/api/partners/opportunities/{uid}/close` | Implementado |
| POST | `/api/partners/opportunities/{uid}/approve` | Alias extra |
| POST | `/api/partners/opportunities/{uid}/reject` | Alias extra |
| POST | `/api/partners/opportunities/{uid}/convert` | Alias extra |
| GET/POST | `/api/partner-resources` | Implementado |
| POST | `/api/partner-resources/{uid}/assign` | Implementado |

## Brechas detectadas contra el documento

1. Partners usaba `type` y `contact_info`; el documento usa `partner_type`, `contact_name`, `contact_email`, `phone`, `region`, `notes`.
2. Partners no aceptaba status `prospect`.
3. Oportunidades usaban `account_uid`, `title`, `amount`, `description` y status `open/won/lost`; el documento usa `client_name`, `client_email`, `product`, `estimated_value`, `registered_date` y status `pending/validated/closed`.
4. `POST /partners/opportunities/validate` existia como validacion de conflicto; faltaba soportar `{ uids: [...] }` para validar en lote.
5. `POST /partners/opportunities/{uid}/close` exigia body con `status`; el documento lo consume sin body.
6. Recursos usaban `type=sales/training`; el documento usa `material_type` con valores de materiales.

## Implementado para cerrar brechas

- Partners:
  - `partner_type` <-> `type`
  - campos planos de contacto se guardan en `contact_info`
  - `registered_opportunities`, `converted_deals`, `joined_date` en response
- Oportunidades:
  - `client_name/client_email` crean o resuelven una cuenta interna cuando no llega `account_uid`
  - `estimated_value` <-> `amount`
  - `product/notes` se preservan en metadata de `description`
  - `pending` se guarda como `open` y se expone como `pending`
  - `validate` por lote cambia oportunidades a `validated`
  - `close` sin body cambia status a `closed`
- Recursos:
  - `material_type` se normaliza a `type`
  - response expone `material_type`, `file_name`, `file_size`, `uploaded_at`, `tags`, `download_count`

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/PartnerController.php`
- `app/Http/Controllers/Api/PartnerResourceController.php`
- `app/Services/PartnerService.php`
- `app/Services/PartnerOpportunityService.php`
- `app/Services/PartnerResourceService.php`
- `app/Models/Partner.php`
- `app/Models/PartnerOpportunity.php`
- `app/Models/PartnerResource.php`

## Pruebas

- `tests/Feature/PartnersBackendIntegrationTest.php`
