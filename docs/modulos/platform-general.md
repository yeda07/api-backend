# Plataforma general

## Estado actual

El backend ya tenia autenticacion, `/api/me`, RBAC, usuarios, localization y endpoints base para listas de usuarios, productos, cuentas, price books, roles y competidores. Faltaba un payload de inicializacion para reemplazar el mock del frontend y faltaban endpoints transversales de opciones por tenant.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| POST | `/api/login` | Implementado |
| GET | `/api/me` | Implementado |
| GET | `/api/auth/init` | Implementado |
| GET/PUT | `/api/settings/localization` | Implementado |
| GET | `/api/users` | Implementado |
| GET | `/api/rbac/roles` | Implementado |
| GET | `/api/products` | Implementado |
| GET | `/api/accounts` | Implementado |
| GET | `/api/price-books` | Implementado |
| GET | `/api/competitive-intelligence/competitors` | Implementado |

## Brechas detectadas contra el documento

1. Faltaba un endpoint de inicializacion con `user`, `tenant`, `modules` y `localization`.
2. Localization no exponia `currency_symbol`, `locale` y `language`.
3. Faltaban endpoints dinamicos para listas transversales de tenant:
   - `/api/tenant/payment-methods`
   - `/api/tenant/lead-origins`
   - `/api/tenant/institution-types`
   - `/api/tenant/company-sizes`
   - `/api/tenant/industries`
   - `/api/tenant/opportunity-products`
   - `/api/tenant/lost-reason-categories`
   - `/api/tenant/activity-types`
   - `/api/tenant/commission-plan-types`

## Implementado para cerrar brechas

- `GET /api/auth/init`
  - `user.uid`, `name`, `email`, `role`, `avatar_url`
  - `tenant.uid`, `name`, `plan`, `logo_url`
  - `modules[]` calculado desde permisos efectivos del usuario
  - `localization` listo para seedear el frontend
- `GET /api/settings/localization`
  - ahora incluye `currency_symbol`, `locale`, `language`
- Endpoints `/api/tenant/*` con formato:

```json
[
  { "uid": "uuid-estable", "name": "Nombre visible", "key": "clave" }
]
```

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/SettingsController.php`
- `app/Http/Controllers/Api/TenantOptionController.php`
- `app/Services/PlatformInitService.php`
- `app/Services/TenantOptionService.php`

## Pruebas

- `tests/Feature/PlatformGeneralIntegrationTest.php`
