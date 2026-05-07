# Inteligencia competitiva

## Estado actual

El backend tiene modulo completo bajo el prefijo correcto `/api/competitive-intelligence`. No se usa `/api/intelligence`.

## Que ya existe

| Metodo | Endpoint | Estado |
|---|---|---|
| GET/POST | `/api/competitive-intelligence/competitors` | Implementado |
| PUT/DELETE | `/api/competitive-intelligence/competitors/{uid}` | Implementado |
| GET/POST | `/api/competitive-intelligence/battlecards` | Implementado |
| GET | `/api/competitive-intelligence/competitors/{uid}/battlecards` | Implementado |
| PUT/DELETE | `/api/competitive-intelligence/battlecards/{uid}` | Implementado |
| GET/POST | `/api/competitive-intelligence/lost-reasons` | Implementado |
| PUT/DELETE | `/api/competitive-intelligence/lost-reasons/{uid}` | Implementado |
| GET | `/api/competitive-intelligence/lost-reasons/report` | Implementado |

## Brechas detectadas contra el documento

1. Competitors exigia `key`; el documento solo envia `name`, `description`, `website`, `strength_score`.
2. Competitors usaba `notes`; el documento usa `description`.
3. Battlecards usaba `summary`, `differentiators`, `objection_handlers`, `recommended_actions`; el documento usa `description`, `strengths`, `weaknesses`, `objections`.
4. Lost reasons usaba `reason_type`, `details`, `estimated_value`, `lost_at`; el documento usa `lost_reason_category`, `lost_reason_detail`, `deal_value`, `closed_date`.
5. Faltaban aliases de respuesta como `competitor_name`, `account_name`, `sales_rep` y categorias en espanol.

## Implementado para cerrar brechas

- Competitors:
  - `description` <-> `notes`
  - `key` se genera desde `name` si no llega
  - `strength_score` se expone como campo calculado
- Battlecards:
  - `description` <-> `summary`
  - `strengths` <-> `differentiators`
  - `weaknesses` <-> `recommended_actions`
  - `objections` <-> `objection_handlers`
  - `competitor_name` en respuesta
- Lost reasons:
  - `lost_reason_category` <-> `reason_type`
  - `lost_reason_detail` <-> `details`
  - `deal_value` <-> `estimated_value`
  - `closed_date` <-> `lost_at`
  - `account_name`, `competitor_name`, `sales_rep` en respuesta

## Archivos principales

- `routes/api.php`
- `app/Http/Controllers/Api/CompetitiveIntelligenceController.php`
- `app/Services/CompetitiveIntelligenceService.php`
- `app/Models/Competitor.php`
- `app/Models/Battlecard.php`
- `app/Models/LostReason.php`

## Pruebas

- `tests/Feature/CompetitiveIntelligenceBackendIntegrationTest.php`
