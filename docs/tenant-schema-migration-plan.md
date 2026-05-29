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

Estado en codigo:

- `TENANCY_MODE=shared` sigue siendo el valor recomendado.
- `TenantSchemaService::createSchema()` crea el schema si la conexion es PostgreSQL.
- `tenants:schemas:provision` permite generar/backfillear `schema_name` sin cambiar el runtime.
- Existe una primera migracion tenant para CRM/pipeline en `database/migrations/tenant/2026_05_28_000001_create_tenant_crm_pipeline_tables.php`.

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

Estado en codigo:

- `config/tenancy.php` contiene `global_tables` y `tenant_tables`.
- El manifiesto es explicito para evitar mover tablas administrativas por accidente.

## Fase 3 - Migraciones tenant-aware

- Separar migraciones de plataforma y migraciones tenant.
- Crear comando para ejecutar migraciones dentro de un schema especifico.
- Usar una tabla `tenant_migrations` dentro de cada schema para que cada tenant tenga su propio historial.
- No eliminar `tenant_id` al inicio; mantenerlo durante la transicion como cinturon de seguridad.

Comandos disponibles:

```bash
php artisan tenants:migrate --tenant_uid=TENANT_UID --pretend
php artisan tenants:migrate --tenant_uid=TENANT_UID
# Sin --tenant_uid migra todos los tenants.
php artisan tenants:migrate
```

## Fase 4 - Migracion de datos

- Congelar escrituras por tenant durante la ventana de migracion.
- Copiar datos desde `public` al schema del tenant.
- Validar conteos por tabla.
- Validar checksums simples en tablas criticas.
- Activar tenant en modo schema de forma individual.

Comando de preparacion/copia:

```bash
# Solo simula y muestra conteos. No copia datos.
php artisan tenants:schemas:copy-data TENANT_UID

# Simula solo algunas tablas.
php artisan tenants:schemas:copy-data TENANT_UID --tables=accounts,contacts,opportunities

# Copia realmente. Usar solo cuando las tablas tenant ya existen.
php artisan tenants:schemas:copy-data TENANT_UID --execute

# Copia realmente limpiando antes las tablas destino. Usar solo en ventana controlada.
php artisan tenants:schemas:copy-data TENANT_UID --execute --truncate
```

Regla importante: el comando no crea tablas operativas con `LIKE public...`; solo copia si la tabla destino ya existe en el schema tenant. Esto reduce riesgos de defaults, secuencias y llaves foraneas mal clonadas.

El comando copia usando columnas explicitas comunes entre `public.tabla` y `tenant_schema.tabla`, no `SELECT *`. Esto permite que PostgreSQL tenga distinto orden fisico de columnas sin romper la copia.

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

## Siguiente paso recomendado

1. Crear migraciones tenant reales en `database/migrations/tenant`.
2. Empezar con el subconjunto ya creado: `accounts`, `contacts`, `crm_entities`, `relations`, `opportunity_stages`, `opportunities`, `tasks`, `activities`.
3. Ejecutar:

```bash
php artisan tenants:schemas:provision --tenant_uid=TENANT_UID
php artisan tenants:migrate --tenant_uid=TENANT_UID --pretend
php artisan tenants:migrate --tenant_uid=TENANT_UID
php artisan tenants:schemas:copy-data TENANT_UID --tables=accounts,contacts,crm_entities,relations,opportunity_stages,opportunities,tasks,activities
```

4. Revisar que el dry-run reporte `tenant_exists=true` para esas tablas.
5. Copiar con `--execute` solo en tenant de prueba.
