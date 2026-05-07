# Proyectos

## Estado actual

El backend tiene proyectos, milestones, asignaciones de equipo y calculo de progreso. Las rutas coinciden con el documento: milestones se crean anidados bajo proyecto y se actualizan por ruta standalone `/api/milestones/{uid}`.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET/POST | `/api/projects` | Implementado |
| GET/PUT | `/api/projects/{uid}` | Implementado |
| POST | `/api/projects/{uid}/milestones` | Implementado |
| PUT | `/api/milestones/{uid}` | Implementado |
| POST | `/api/projects/{uid}/assignments` | Implementado |
| GET | `/api/projects/{uid}/team` | Implementado |
| GET | `/api/projects/{uid}/progress` | Implementado |

## Brechas detectadas contra el documento

1. El backend usaba `account_uid`; el documento usa `client_uid` y espera `client_name`.
2. El backend usaba estados `pending/active/completed`; el documento usa `planning/in_progress/on_hold/completed/cancelled`.
3. El documento espera `priority`, `assigned_to_uid`, `assigned_to_name`, `estimated_hours`, `actual_hours` en proyecto.
4. Milestones usaban `name`; el documento usa `title`.
5. Milestones usaban `done`; el documento usa `completed`.
6. Assignments exigia roles internos `consultant/tech/manager` y `hours_allocated`; el documento usa roles como `developer` y no exige horas.
7. Progress devolvia estructura anidada; el documento espera campos planos como `completion_pct`, `milestones_total`, `hours_estimated`.

## Implementado para cerrar brechas

- Projects:
  - `client_uid` <-> `account_uid`
  - `client_name` en response
  - `pending` se expone como `planning`
  - `active` se expone como `in_progress`
  - `assigned_to_uid` crea una asignacion principal
  - `estimated_hours` se calcula desde asignaciones
  - `actual_hours` se expone como `0.0` hasta que exista timesheet
- Milestones:
  - `title` <-> `name`
  - `completed` <-> `done`
  - `assigned_to_uid` se acepta como campo de compatibilidad
- Assignments:
  - roles `developer`, `designer`, `qa`, `analyst` aceptados
  - `hours_allocated` ahora es opcional y por defecto `0`
  - response incluye `user_name` y `assigned_at`
- Progress:
  - se mantienen campos existentes
  - se agregan `completion_pct`, `milestones_total`, `milestones_completed`, `hours_estimated`, `hours_logged`

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/ProjectController.php`
- `app/Services/ProjectService.php`
- `app/Services/MilestoneService.php`
- `app/Services/AssignmentService.php`
- `app/Services/ProgressService.php`
- `app/Models/Project.php`
- `app/Models/ProjectMilestone.php`
- `app/Models/ProjectAssignment.php`

## Pruebas

- `tests/Feature/ProjectsBackendIntegrationTest.php`
- `tests/Feature/ProjectManagementTest.php`
