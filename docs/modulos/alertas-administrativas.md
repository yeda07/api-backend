# Alertas administrativas

## Estado actual

El modulo administra reglas globales de telemetria para superadmin. El CRUD ya existia y ahora tambien soporta el contrato estructurado del frontend, evaluacion de reglas activas, notificaciones best-effort y scheduler.

## Rutas principales

| Metodo | Endpoint | Permiso | Uso |
|---|---|---|---|
| GET | `/api/admin/telemetry/summary` | `admin.telemetry.read` | Resumen global de telemetria |
| GET | `/api/admin/telemetry/stats` | `admin.telemetry.read` | Uptime y latencia p95 |
| GET | `/api/admin/telemetry/logs` | `admin.telemetry.read` | Logs filtrables |
| GET | `/api/admin/telemetry/alerts` | `admin.alerts.manage` | Listar reglas |
| POST | `/api/admin/telemetry/alerts` | `admin.alerts.manage` | Crear regla |
| PUT | `/api/admin/telemetry/alerts/{uid}` | `admin.alerts.manage` | Actualizar regla |
| POST | `/api/admin/telemetry/alerts/{uid}/toggle` | `admin.alerts.manage` | Activar/desactivar |
| POST | `/api/admin/telemetry/alerts/evaluate` | `admin.alerts.manage` | Evaluacion manual |

## Contrato de regla

```json
{
  "uid": "uuid",
  "nombre": "Errores criticos",
  "metric": "errores",
  "operator": ">",
  "value": 50,
  "period": "1h",
  "canales": ["EMAIL", "SLACK"],
  "estado": "ACTIVO",
  "last_triggered_at": "2026-05-06T10:00:00Z"
}
```

Valores soportados:

- `metric`: `errores`, `warnings`, `latencia`, `uptime`
- `operator`: `>`, `<`, `>=`, `<=`
- `period`: `1h`, `6h`, `24h`, `7d`
- `canales`: `EMAIL`, `SLACK`, `PUSH`
- `estado`: `ACTIVO`, `INACTIVO`

Tambien se mantiene `condicion` para compatibilidad con reglas viejas. Si el frontend envia estructura, el backend genera `condition_text` legible.

## Como se evalua

El servicio `AdminAlertEvaluatorService` busca reglas con:

- `is_active = true`
- `metric`, `operator`, `value`, `period` no nulos

Luego calcula el valor real desde `system_logs`:

- `errores`: cantidad de logs `critical` o `error` en el periodo.
- `warnings`: cantidad de logs `warning` en el periodo.
- `latencia`: p95 de `latency_ms`, `duration_ms`, `response_time_ms` o `elapsed_ms` en `context`.
- `uptime`: porcentaje calculado como `1 - errores / total_logs`.

Si la comparacion se cumple:

- Actualiza `last_triggered_at`.
- Registra log con `LoggerService`.
- Intenta notificar por canales configurados.

## Notificaciones

Canales:

- `EMAIL`: usa Laravel Mail. Destino: `ADMIN_ALERT_EMAIL`, con fallback a `MAIL_FROM_ADDRESS`.
- `SLACK`: usa webhook `ADMIN_ALERT_SLACK_WEBHOOK_URL`.
- `PUSH`: queda registrado en logs como placeholder operativo.

Variables opcionales:

```env
ADMIN_ALERT_EMAIL=ops@example.com
ADMIN_ALERT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

Las notificaciones son best-effort: si fallan, no rompen el request ni el scheduler.

## Scheduler y comando

Comando:

```bash
php artisan admin-alerts:evaluate
```

Scheduler:

- Definido en `bootstrap/app.php`.
- Corre cada 5 minutos.
- Usa `withoutOverlapping()`.

## Archivos de backend

- `routes/api.php`
- `routes/console.php`
- `bootstrap/app.php`
- `config/services.php`
- `app/Http/Controllers/Api/AdminTelemetryController.php`
- `app/Services/AdminAlertEvaluatorService.php`
- `app/Models/AdminAlertRule.php`
- `app/Models/SystemLog.php`
- `database/migrations/2026_04_27_000100_add_platform_admin_support.php`
- `database/migrations/2026_05_06_000002_add_structured_fields_to_admin_alert_rules.php`

## Pruebas

- `tests/Feature/AdminAlertEvaluatorTest.php`

Cobertura:

- Crear regla con contrato estructurado.
- Evaluar contra logs reales.
- Actualizar `last_triggered_at`.
- Ignorar reglas inactivas o no cumplidas.

