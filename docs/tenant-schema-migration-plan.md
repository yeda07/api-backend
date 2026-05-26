# Plan de migracion a schema por tenant

## Objetivo

Separar los datos operativos de cada tenant en un schema PostgreSQL propio, manteniendo en `public` la informacion de plataforma necesaria para autenticar, facturar y administrar tenants.

## Estado actual

- La aplicacion usa una base compartida con columna `tenant_id`.
- Los modelos tenant-aware usan `TenantScope`.
- `public` contiene tanto datos de plataforma como datos operativos de tenants.

## Fase 1 - Preparacion segura

- Agregar `tenants.schema_name`.
- Provisionar schema vacio al crear tenant.
- Agregar comando para provisionar schemas de tenants existentes.
- Mantener `TENANCY_MODE=shared`, sin cambiar runtime ni mover datos.

## Fase 2 - Clasificacion de tablas

Tablas que se quedan en `public`:

- tenants
- plans
- currencies
- permissions
- admin roles / permisos de plataforma
- users, tokens y sesiones mientras auth siga siendo global

Tablas candidatas a schema por tenant:

- accounts
- contacts
- crm_entities
- opportunities
- activities
- tasks
- quotations
- invoices
- inventory
- projects
- relations
- custom fields
- documents metadata
- reports operativos

## Fase 3 - Migraciones tenant-aware

- Separar migraciones de plataforma y migraciones tenant.
- Crear comando para ejecutar migraciones dentro de un schema especifico.
- Usar una tabla `tenant_migrations` dentro de cada schema para que cada tenant tenga su propio historial.
- No eliminar `tenant_id` al inicio; mantenerlo durante la transicion como cinturon de seguridad.

## Fase 4 - Migracion de datos

- Congelar escrituras por tenant durante la ventana de migracion.
- Copiar datos desde `public` al schema del tenant.
- Validar conteos por tabla.
- Validar checksums simples en tablas criticas.
- Activar tenant en modo schema de forma individual.

## Fase 5 - Switching runtime

- Resolver tenant por usuario autenticado.
- Setear `search_path` a `"schema_tenant", public` solo cuando el tenant este migrado.
- Resetear `search_path` al final de cada request y job.
- Mantener fallback a modo shared para tenants no migrados.

## Fase 6 - Limpieza

- Cuando todos los tenants esten migrados, evaluar remover dependencias fuertes de `tenant_id` en tablas operativas.
- Mantener `tenant_id` si aporta auditoria o consultas cross-tenant controladas.

## Regla de seguridad

No activar `TENANCY_MODE=schema` globalmente hasta que:

- Exista migracion tenant-aware probada.
- Los jobs/queues reseteen `search_path`.
- El login/auth/init funcionen desde `public`.
- Exista rollback por tenant.
