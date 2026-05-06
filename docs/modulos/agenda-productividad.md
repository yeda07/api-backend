# Agenda y productividad

## Estado actual

El modulo cubre agenda de actividades, interacciones/timeline y documentos asociados a entidades. Las rutas base ya existian y se alineo Activities con el contrato del frontend para tipos, estados, prioridad y aliases de campos.

## Activities

### Rutas

| Metodo | Endpoint | Permiso | Uso |
|---|---|---|---|
| GET | `/api/activities` | `activities.read` | Listar actividades |
| GET | `/api/activities/range` | `activities.read` | Filtrar por rango |
| GET | `/api/activities/{uid}` | `activities.read` | Ver actividad |
| POST | `/api/activities` | `activities.create` | Crear actividad |
| PUT | `/api/activities/{uid}` | `activities.update` | Actualizar actividad |
| DELETE | `/api/activities/{uid}` | `activities.delete` | Eliminar actividad |

### Contrato

Tipos aceptados:

- `task`
- `call`
- `meeting`
- `email`
- `note`
- `reminder` se mantiene por compatibilidad.

Estados aceptados:

- `pending`
- `in_progress`
- `completed`
- `cancelled`
- `overdue` se mantiene porque el backend lo calcula para actividades vencidas.

Prioridad:

- `low`
- `medium`
- `high`

Payload compatible con frontend:

```json
{
  "type": "call",
  "title": "Llamada de seguimiento",
  "description": "Contactar para renovacion",
  "status": "in_progress",
  "priority": "high",
  "scheduled_at": "2026-05-10T10:00:00Z",
  "contact_uid": "uuid",
  "account_uid": "uuid",
  "assigned_to_uid": "uuid"
}
```

Tambien se aceptan los nombres internos previos:

- `assigned_user_uid`
- `entity_type`
- `entity_uid`

Respuesta relevante:

- `uid`
- `type`
- `title`
- `description`
- `status`
- `priority`
- `scheduled_at`
- `completed_at`
- `contact_uid`
- `contact_name`
- `account_uid`
- `account_name`
- `assigned_to_uid`
- `assigned_to_name`
- `assigned_user_uid`
- `activityable_uid`
- `created_at`

### Range

`GET /api/activities/range` acepta ambos formatos:

```text
?start=2026-05-01&end=2026-05-15
?from=2026-05-01&to=2026-05-15
```

### Logica interna

- `ActivityService` sincroniza actividades vencidas antes de listar/consultar.
- Si una actividad `pending` tiene `scheduled_at < now()`, pasa a `overdue`.
- Si el status cambia a `completed`, se llena `completed_at`.
- Si el status deja de ser `completed`, `completed_at` vuelve a `null`.
- Si cambia el status y la actividad esta asociada a una entidad, se registra una interaccion de cambio de estado.

## Interactions

### Rutas

| Metodo | Endpoint | Permiso | Uso |
|---|---|---|---|
| GET | `/api/interactions/{type}/{uid}` | `interactions.read` | Timeline por entidad |
| POST | `/api/interactions/notes` | `interactions.create` | Crear nota |
| POST | `/api/interactions/calls` | `interactions.create` | Registrar llamada |
| POST | `/api/interactions/emails` | `interactions.create` | Registrar email |

Las interacciones no tienen CRUD plano. Se crean y consultan anidadas por entidad.

Tipos de entidad resueltos por helper:

- `account`
- `contact`
- `crm_entity`

## Documents

### Rutas

| Metodo | Endpoint | Permiso | Uso |
|---|---|---|---|
| GET | `/api/documents/entity/{type}/{uid}` | `documents.read` | Documentos por entidad |
| GET | `/api/documents/account/{accountUid}` | `documents.read` | Documentos por cuenta |
| GET | `/api/documents/missing/{accountUid}` | `documents.read` | Documentos requeridos faltantes |
| GET | `/api/documents/download/{uid}` | `documents.read` | Descargar archivo |
| GET | `/api/documents/{uid}/versions` | `documents.read` | Versiones |
| GET | `/api/documents/{uid}` | `documents.read` | Ver documento |
| POST | `/api/documents` | `documents.create` | Upload multipart |
| PUT | `/api/documents/{uid}` | `documents.manage` | Actualizar metadata/version |
| GET/POST/PUT | `/api/document-types` | `documents.read/manage` | Tipos documentales |
| GET | `/api/document-alerts` | `documents.read` | Alertas documentales |
| POST | `/api/document-alerts/generate` | `documents.manage` | Generar alertas |
| POST | `/api/document-alerts/{uid}/read` | `documents.manage` | Marcar alerta como leida |

## Archivos de backend

Activities:

- `routes/api.php`
- `app/Http/Controllers/Api/ActivityController.php`
- `app/Services/ActivityService.php`
- `app/Models/Activity.php`
- `database/migrations/2026_04_08_000007_create_interactions_activities_documents_tables.php`
- `database/migrations/2026_05_06_000003_add_priority_to_activities.php`

Interactions:

- `app/Http/Controllers/Api/InteractionController.php`
- `app/Services/InteractionService.php`
- `app/Models/Interaction.php`
- `app/Helpers/helpers.php`

Documents:

- `app/Http/Controllers/Api/DocumentController.php`
- `app/Http/Controllers/Api/DocumentTypeController.php`
- `app/Http/Controllers/Api/DocumentAlertController.php`
- `app/Services/DocumentService.php`
- `app/Services/DocumentTypeService.php`
- `app/Services/DocumentAlertService.php`
- `app/Models/Document.php`
- `app/Models/DocumentType.php`
- `app/Models/DocumentAlert.php`
- `app/Models/AlertRule.php`

## Pruebas

- `tests/Feature/AgendaBackendIntegrationTest.php`
- `tests/Feature/DashboardCoreIntegrationTest.php`

Cobertura:

- Crear/actualizar/eliminar actividades con contrato del frontend.
- `contact_uid`, `account_uid` y `assigned_to_uid`.
- Respuesta plana con nombres de contacto/cuenta/asignado.
- Rango con `start/end`.
- Compatibilidad de scope de actividades usado por dashboard.

