# Filter Equipo

Revision e implementacion para frontend.

## Estado general

El endpoint ya existia:

`GET /api/teams`

Antes devolvia todos los equipos del tenant autenticado sin aceptar filtros. Ahora acepta busqueda por nombre.

## Endpoint actualizado

`GET /api/teams?search=nombre_equipo`

Permiso requerido:

`teams.read`

La busqueda se aplica sobre `name` del equipo y es case-insensitive.

## Respuesta

Mantiene el contrato existente de equipos:

- `uid`
- `name`
- `description`
- `is_active`
- `manager_uid`
- `manager_name`
- `leader_uid`
- `leader_name`
- `members_count`
- `member_uids`
- `members`

## Ejemplo

Request:

`GET /api/teams?search=norte`

Respuesta esperada:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uid": "uuid",
      "name": "Equipo Norte",
      "description": null,
      "is_active": true,
      "manager_uid": null,
      "manager_name": null,
      "leader_uid": null,
      "leader_name": null,
      "members_count": 0,
      "member_uids": [],
      "members": []
    }
  ],
  "meta": null,
  "errors": null
}
```

## Pruebas

Validado con:

`php artisan test --filter=SettingsBackendIntegrationTest`
