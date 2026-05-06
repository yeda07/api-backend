# Questions for Backend

Documento de diagnostico basado en `QUESTIONS_FOR_BACKEND.md`.

## Resumen ejecutivo

El backend ya cubre varias piezas con otra estructura, pero hay diferencias de contrato con el frontend. Se implementan las brechas directas y seguras, y quedan como pendiente estructural los modulos que requieren una entidad nueva completa.

## 1. Segments

Estado: implementado.

Lo que hay:

- `/crm-entities` existe, pero no es lo mismo que segmentos.
- El backend tiene busqueda dinamica y filtros en varios index, pero no filtros guardados reutilizables.

Agregado:

- CRUD `/segments`.
- Persistencia tenant-scoped de `rules` y `logic`.
- `POST /segments/{uid}/run` para evaluar reglas en backend.
- Soporte para `account`, `contact` y `crm_entity`.

## 2. Automation

Estado: implementado.

Agregado:

- CRUD `/automation/rules`.
- Toggle `/automation/rules/{uid}/toggle`.
- CRUD `/automation/assignment-rules`.
- Motor de reglas server-side reutilizable.

Pendiente opcional:

- Conectar `AutomationService::execute()` a eventos reales del dominio.

## 3. Teams

Estado: implementado.

Lo que hay:

- RBAC con roles/permisos.
- Jerarquia de usuarios mediante manager/subordinados.

Agregado:

- Entidad `Team`.
- CRUD `/teams`.
- Manager por usuario.
- Membresias por `member_uids`.

## 4. Custom Fields

Estado: implementado.

Lo que habia:

- `POST /custom-fields`
- `POST /custom-fields/value`

Agregado:

- `GET /custom-fields`
- `PUT /custom-fields/{uid}`
- `DELETE /custom-fields/{uid}`

## 5. Localization

Estado: implementado.

Agregado:

- `GET /settings/localization`
- `PUT /settings/localization`

Se maneja a nivel tenant, con `user_timezone` como override del usuario autenticado.

## 6. Agenda y Projects

Estado: cubierto con endpoints existentes.

Lo que hay:

- Projects y milestones reales.
- Activities e interactions con entidad relacionada.

Decision:

- Agenda puede consumir milestones desde Projects.
- No se agrega endpoint duplicado de milestones en Agenda por ahora.

## 7. Interacciones y Documentos planos

Estado: implementado.

Agregado:

- `GET /interactions`
- `GET /documents`
- `DELETE /documents/{uid}`

Se mantienen los endpoints anidados existentes.

## 8. Partner Opportunities

Estado: implementado con aliases.

Lo que habia:

- `POST /partners/opportunities/validate`
- `POST /partners/opportunities/{uid}/close`

Agregado:

- `POST /partners/opportunities/{uid}/approve`
- `POST /partners/opportunities/{uid}/reject`
- `POST /partners/opportunities/{uid}/convert`

Mapeo:

- `approve` -> close con `status = won`
- `reject` -> close con `status = lost`
- `convert` -> close con `status = won`

## 9. Reports

Estado: parcialmente implementado con aliases.

Lo que hay:

- Reportes por modulo.

Agregado:

- `GET /reports/inventory` -> alias de `InventoryService::report`
- `GET /reports/sales` -> alias de dashboard financiero

## 10. Commissions

Estado: backend mas completo que frontend.

No se implementa nada nuevo. El frontend puede expandirse para consumir targets, rules y entries cuando producto lo necesite.

## 11. UID vs ID

Estado: alineado.

El backend oculta IDs internos en la mayoria de modelos y expone `uid` publico.

## 12. snake_case

Estado: alineado.

El backend responde principalmente en `snake_case`. Hay algunos campos heredados o especificos de frontend, pero no se detecta un patron camelCase general.
