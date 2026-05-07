# Documentacion por modulo

Esta carpeta resume como se esta manejando cada modulo del backend, que endpoints expone, que servicios/modelos lo soportan, que contrato JSON espera el frontend y que pruebas cubren la integracion.

## Archivos

- [Inventario](inventario.md)
- [Alertas administrativas](alertas-administrativas.md)
- [Agenda y productividad](agenda-productividad.md)
- [Questions for Backend](questions-backend.md)
- [Comisiones](comisiones.md)
- [Contactos y CRM](contactos-crm.md)
- [Dashboard](dashboard.md)
- [Inteligencia competitiva](inteligencia-competitiva.md)
- [Partners](partners.md)
- [Plataforma general](platform-general.md)
- [Proyectos](proyectos.md)

## Convenciones generales

- Base path API: `/api`.
- Autenticacion: rutas protegidas con `auth:sanctum`.
- Multi-tenant: los modelos de negocio usan `tenant_id` y scopes de tenant cuando aplica.
- Permisos: cada endpoint declara permisos con middleware `permission:*`.
- Respuestas JSON: formato comun con `success`, `message`, `data`, `meta` y `errors`, salvo respuestas puntuales que agregan campos de primer nivel requeridos por frontend, como `summary` en bodegas.
- UID publico: el frontend usa `uid`, no IDs internos.
