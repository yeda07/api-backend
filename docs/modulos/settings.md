# Settings

## Documento revisado

- Fuente: `required-backend/settings.md`
- Modulo: Settings
- Alcance: tags, custom fields, teams y localization.

## Que ya tenia el backend

- Tags:
  - `GET /api/tags`
  - `POST /api/tags`
  - `PUT /api/tags/{uid}`
  - `DELETE /api/tags/{uid}`
  - `POST /api/tags/assign`
  - `POST /api/tags/unassign`
- Custom Fields:
  - `GET /api/custom-fields`
  - `POST /api/custom-fields`
  - `PUT /api/custom-fields/{uid}`
  - `DELETE /api/custom-fields/{uid}`
  - `POST /api/custom-fields/value`
- Teams:
  - `GET /api/teams`
  - `POST /api/teams`
  - `GET /api/teams/{uid}`
  - `PUT /api/teams/{uid}`
  - `DELETE /api/teams/{uid}`
- Localization:
  - `GET /api/settings/localization`
  - `PUT /api/settings/localization`

## Que hacia falta segun el documento

- Tags:
  - persistir `entity_types[]`
  - normalizar `color` a hex para evitar inconsistencia entre Settings y Dashboard
- Teams:
  - endpoints explicitos para agregar/remover miembros:
    - `POST /api/teams/{uid}/members`
    - `DELETE /api/teams/{uid}/members/{user_uid}`
  - aliases `leader_uid`, `leader_name`, `members_count`
  - impedir borrar equipos con miembros
- Localization:
  - usar permiso `settings.manage`
  - persistir `locale`
- Custom Fields:
  - agregar aliases frontend `label`, `module`, `required` y soporte de `module` en request.

## Implementado

### Tags

- `entity_types` se guarda como arreglo JSON.
- `color` acepta nombres (`green`, `blue`, `red`, etc.) y se normaliza a hex.

### Teams

| Metodo | Endpoint | Permiso | Estado |
| --- | --- | --- | --- |
| POST | `/api/teams/{uid}/members` | `teams.manage` | Implementado |
| DELETE | `/api/teams/{uid}/members/{userUid}` | `teams.manage` | Implementado |

La respuesta agrega:

- `leader_uid`
- `leader_name`
- `members_count`
- `members[]` con `user_uid`, `user_name`, `role_name`, `assigned_clients`

### Localization

- `GET /api/settings/localization` ahora requiere `settings.manage`.
- `PUT /api/settings/localization` ahora requiere `settings.manage`.
- `locale` se persiste en tenants.

### Custom Fields

- Se conservan los campos originales y se agregan aliases:
  - `label`
  - `module`
  - `required`
  - `select_options`
- El request puede usar `module` y `label`.

## Archivos principales

- `app/Services/TagService.php`
- `app/Models/Tag.php`
- `app/Services/TeamService.php`
- `app/Models/Team.php`
- `app/Services/CustomFieldService.php`
- `app/Models/CustomField.php`
- `app/Http/Controllers/Api/SettingsController.php`
- `database/migrations/2026_05_07_000007_add_entity_types_to_tags.php`
- `database/migrations/2026_05_07_000008_add_locale_to_tenants_table.php`
- `tests/Feature/SettingsBackendIntegrationTest.php`

## Pendiente

- No queda pendiente bloqueante del documento.
- `entity_types` queda como metadato global de tag; el filtrado automatico por contexto se puede aplicar en frontend o en una futura query `?entity_type=...`.
