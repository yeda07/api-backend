<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAlertRule;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Support\ApiIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminTelemetryController extends Controller
{
    public function summary()
    {
        $logs24h = SystemLog::withoutGlobalScopes()
            ->with('tenant')
            ->where('created_at', '>=', now()->subDay())
            ->get();

        $errorLogs = $logs24h->whereIn('level', ['critical', 'error']);
        $warningLogs = $logs24h->where('level', 'warning');
        $latencies = $logs24h
            ->map(fn (SystemLog $log) => $this->extractLatencyMs($log))
            ->filter(fn ($value) => $value !== null)
            ->sort()
            ->values();

        $totalLogs = $logs24h->count();
        $uptime = $totalLogs > 0
            ? round(max(0, 1 - ($errorLogs->count() / $totalLogs)) * 100, 2)
            : 100.0;

        $errorsByTenant = $errorLogs
            ->groupBy('tenant_id')
            ->map(function ($items) {
                $last = $items->sortByDesc('created_at')->first();
                $mostFrequentMessage = $items
                    ->groupBy('message')
                    ->sortByDesc(fn ($group) => $group->count())
                    ->keys()
                    ->first();

                return [
                    'tenant_uid' => $last?->tenant?->uid,
                    'tenant_nombre' => $last?->tenant?->name ?? 'Plataforma',
                    'errors_24h' => $items->count(),
                    'tipo_mas_frecuente' => $mostFrequentMessage,
                    'ultimo_error_at' => optional($last?->created_at)?->toISOString(),
                    'severity' => $items->contains('level', 'critical') ? 'CRITICO' : 'ALTO',
                    'estado' => $last?->tenant?->status,
                ];
            })
            ->sortByDesc('errors_24h')
            ->values();

        return $this->successResponse([
            'uptime_global' => $uptime,
            'sla' => $uptime,
            'errors_24h' => $errorLogs->count(),
            'warnings_24h' => $warningLogs->count(),
            'tenants_with_errors' => $errorLogs->pluck('tenant_id')->filter()->unique()->count(),
            'latency_p95_ms' => $this->percentile($latencies, 95),
            'active_alerts' => AdminAlertRule::query()->where('is_active', true)->count(),
            'errors_by_tenant' => $errorsByTenant,
        ]);
    }

    public function logs(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'tenant_uid' => 'nullable|uuid',
            'nivel' => 'nullable|string|in:ERROR,WARN,INFO',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $query = SystemLog::withoutGlobalScopes()->with('tenant')->latest();

        if (!empty($validated['tenant_uid'])) {
            $tenantId = Tenant::query()->where('uid', $validated['tenant_uid'])->value('id');
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($validated['nivel'])) {
            $query->where('level', $this->toLogLevel($validated['nivel']));
        }

        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $result = ApiIndex::paginateOrGet($query, $validated, 'telemetry_logs_page');

        if ($result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $mapped = $result->getCollection()->map(fn (SystemLog $log) => $this->serializeLog($log));
            $result->setCollection($mapped);

            return $this->successResponse($result);
        }

        return $this->successResponse(collect($result)->map(fn (SystemLog $log) => $this->serializeLog($log))->values());
    }

    public function alerts()
    {
        return $this->successResponse(AdminAlertRule::query()->latest()->get()->map(fn (AdminAlertRule $rule) => $this->serializeAlert($rule))->values());
    }

    public function storeAlert(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'condicion' => 'required|string|max:255',
                'canales' => 'required|array|min:1',
                'canales.*' => 'string|in:EMAIL,SLACK,PUSH',
                'estado' => 'nullable|string|in:ACTIVO,INACTIVO',
            ]);

            $rule = AdminAlertRule::query()->create([
                'name' => $validated['nombre'],
                'condition_text' => $validated['condicion'],
                'channels' => $validated['canales'],
                'is_active' => ($validated['estado'] ?? 'ACTIVO') === 'ACTIVO',
            ]);

            return $this->successResponse($this->serializeAlert($rule), 201, 'Alerta creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function updateAlert(Request $request, string $uid)
    {
        try {
            $rule = AdminAlertRule::query()->where('uid', $uid)->first();

            if (!$rule) {
                return $this->errorResponse('Alerta no encontrada', 404);
            }

            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'condicion' => 'sometimes|string|max:255',
                'canales' => 'sometimes|array|min:1',
                'canales.*' => 'string|in:EMAIL,SLACK,PUSH',
                'estado' => 'sometimes|string|in:ACTIVO,INACTIVO',
            ]);

            $rule->update([
                'name' => $validated['nombre'] ?? $rule->name,
                'condition_text' => $validated['condicion'] ?? $rule->condition_text,
                'channels' => $validated['canales'] ?? $rule->channels,
                'is_active' => array_key_exists('estado', $validated) ? $validated['estado'] === 'ACTIVO' : $rule->is_active,
            ]);

            return $this->successResponse($this->serializeAlert($rule->fresh()), 200, 'Alerta actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function toggleAlert(string $uid)
    {
        $rule = AdminAlertRule::query()->where('uid', $uid)->first();

        if (!$rule) {
            return $this->errorResponse('Alerta no encontrada', 404);
        }

        $rule->update([
            'is_active' => !$rule->is_active,
        ]);

        return $this->successResponse($this->serializeAlert($rule->fresh()), 200, 'Estado de alerta actualizado');
    }

    private function serializeLog(SystemLog $log): array
    {
        $errorsLast24h = SystemLog::withoutGlobalScopes()
            ->where('tenant_id', $log->tenant_id)
            ->whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $frontendLevel = match ($log->level) {
            'critical', 'error' => 'ERROR',
            'warning' => 'WARN',
            default => 'INFO',
        };

        return [
            'uid' => $log->uid,
            'tenant_uid' => $log->tenant?->uid,
            'tenant_nombre' => $log->tenant?->name,
            'level' => $frontendLevel,
            'message' => $log->message,
            'timestamp' => optional($log->created_at)?->toISOString(),
            'errors_last_24h' => $errorsLast24h,
            'severity' => match ($log->level) {
                'critical' => 'CRITICO',
                'error' => 'ALTO',
                'warning' => 'MEDIO',
                default => 'BAJO',
            },
        ];
    }

    private function extractLatencyMs(SystemLog $log): ?float
    {
        $context = $log->context ?? [];

        foreach (['latency_ms', 'duration_ms', 'response_time_ms', 'elapsed_ms'] as $key) {
            if (isset($context[$key]) && is_numeric($context[$key])) {
                return (float) $context[$key];
            }
        }

        return null;
    }

    private function percentile($values, int $percentile): ?float
    {
        $values = collect($values)->values();
        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $index = (int) ceil(($percentile / 100) * $count) - 1;

        return round((float) $values->get(max(0, min($index, $count - 1))), 2);
    }

    private function serializeAlert(AdminAlertRule $rule): array
    {
        return [
            'uid' => $rule->uid,
            'nombre' => $rule->name,
            'condicion' => $rule->condition_text,
            'canales' => $rule->channels,
            'estado' => $rule->is_active ? 'ACTIVO' : 'INACTIVO',
            'last_triggered_at' => optional($rule->last_triggered_at)?->toISOString(),
        ];
    }

    private function toLogLevel(string $level): string
    {
        return match ($level) {
            'ERROR' => 'error',
            'WARN' => 'warning',
            default => 'info',
        };
    }
}
