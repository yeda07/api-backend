# Contactos y CRM

## Estado actual

El backend ya tiene CRUD de cuentas, contactos, relaciones, entidades CRM y productos instalados por cuenta. Tambien existe el modulo `/api/segments`, por lo que la duda del documento queda resuelta: segmentacion dinamica se maneja con endpoints propios de `segments`, no con `crm-entities`.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET/POST | `/api/accounts` | Implementado |
| GET/PUT/DELETE | `/api/accounts/{uid}` | Implementado |
| POST | `/api/accounts/{uid}/owner` | Implementado |
| GET/POST | `/api/accounts/{uid}/products` | Implementado |
| GET/POST | `/api/contacts` | Implementado |
| GET/PUT/DELETE | `/api/contacts/{uid}` | Implementado |
| POST | `/api/contacts/{uid}/owner` | Implementado |
| GET/POST/DELETE | `/api/relations` | Implementado |
| GET | `/api/relations/with-entities` | Implementado |
| GET | `/api/relations/hierarchy/{type}/{uid}` | Implementado |
| GET/POST | `/api/crm-entities` | Implementado |
| POST | `/api/crm-entities/{uid}/owner` | Implementado |
| GET/POST/PUT/DELETE | `/api/segments` | Implementado |
| POST | `/api/segments/{uid}/run` | Implementado |

## Brechas detectadas contra el documento

1. Faltaba `POST /api/contacts/check-duplicate`.
2. El documento usa `tax_id` para cuentas; backend guarda `document`.
3. El documento usa `company_uid`, `company_name`, `job_title` y `name` para contactos; backend guarda `account_uid`, relacion `account`, `position`, `first_name` y `last_name`.
4. El documento espera campos informativos como `status`, `type`, `id_number`, `country`, `city`, `company_size`; no todos existen fisicamente en la base actual.

## Implementado para cerrar brechas

- `POST /api/contacts/check-duplicate`
  - Request: `{ "email": "...", "tax_id": "...", "exclude_uid": "..." }`
  - Response: `{ "email_duplicate": true, "tax_id_duplicate": false }`
- Alias de cuenta:
  - Request `tax_id` -> `document`
  - Response `tax_id` desde `document`
- Aliases de contacto:
  - Request `name` -> `first_name`/`last_name`
  - Request `company_uid` -> `account_uid`
  - Request `job_title` -> `position`
  - Response `name`, `company_uid`, `company_name`, `job_title`
- Campos de compatibilidad en response:
  - Contactos: `type`, `status`, `id_number`
  - Cuentas: `status`, `country`, `city`, `company_size`

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/ContactController.php`
- `app/Services/ContactService.php`
- `app/Services/AccountService.php`
- `app/Models/Contact.php`
- `app/Models/Account.php`

## Pruebas

- `tests/Feature/ContactsBackendIntegrationTest.php`
