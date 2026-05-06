# Comisiones

## Estado actual

El backend tiene un modulo de comisiones avanzado con planes, asignaciones, metas, reglas, entradas, eventos financieros, liquidaciones, simulador y dashboards.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET/POST | `/api/commissions/plans` | Implementado |
| PUT | `/api/commissions/plans/{uid}` | Implementado |
| GET/POST | `/api/commissions/assignments` | Implementado |
| PUT | `/api/commissions/assignments/{uid}` | Implementado |
| GET/POST | `/api/commissions/targets` | Implementado |
| GET/POST/PUT/DELETE | `/api/commissions/rules` | Implementado |
| GET | `/api/commissions/entries` | Implementado |
| PUT | `/api/commissions/entries/{uid}/pay` | Implementado |
| POST | `/api/commissions/financial-records` | Implementado |
| GET/POST | `/api/commissions/runs` | Implementado |
| POST | `/api/commissions/runs/{uid}/approve` | Implementado |
| POST | `/api/commissions/runs/{uid}/pay` | Implementado |
| POST | `/api/commissions/simulate` | Implementado |
| GET | `/api/commissions/dashboard/{userUid}` | Implementado |
| GET | `/api/commissions/my-summary` | Implementado |

## Brechas detectadas contra el documento

1. El documento usa nombres frontend `base_percentage`, `tiers`, `is_active`; backend usaba `base_percent`, `tiers_json`, `active`.
2. Faltaban `GET /plans/{uid}` y `DELETE /plans/{uid}`.
3. Faltaban `GET /assignments/{uid}` y `DELETE /assignments/{uid}`.
4. Targets solo tenia list/create; faltaban `GET`, `PUT`, `DELETE`.
5. `POST /commissions/financial-records` del documento usa payload simple (`type`, `amount`, `description`, `recorded_at`); backend exigia payload financiero detallado.
6. `POST /commissions/simulate` del documento usa `plan_uid` y `total_sales`; backend exigia `user_uid` y `sale_amount`.
7. Entries usan internamente `earned/paid`; para frontend se expone `frontend_status`, donde `earned` equivale a `pending`.

## Implementado para cerrar brechas

- Aliases de request/response para planes:
  - `base_percentage` <-> `base_percent`
  - `tiers` <-> `tiers_json`
  - `is_active` <-> `active`
- `GET /api/commissions/plans/{uid}`
- `DELETE /api/commissions/plans/{uid}`
- `GET /api/commissions/assignments/{uid}`
- `DELETE /api/commissions/assignments/{uid}`
- `GET /api/commissions/targets/{uid}`
- `PUT /api/commissions/targets/{uid}`
- `DELETE /api/commissions/targets/{uid}`
- Payload simple para `financial-records`
- Simulacion directa por `plan_uid + total_sales`
- `frontend_status` en commission entries

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/CommissionController.php`
- `app/Services/CommissionService.php`
- `app/Models/CommissionPlan.php`
- `app/Models/CommissionAssignment.php`
- `app/Models/CommissionTarget.php`
- `app/Models/CommissionEntry.php`

## Pruebas

- `tests/Feature/CommissionsBackendIntegrationTest.php`

