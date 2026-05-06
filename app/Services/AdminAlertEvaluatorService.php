<?php

namespace App\Services;

use App\Models\AdminAlertRule;
use App\Models\SystemLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminAlertEvaluatorService
{
    public function evaluateActiveRules(): array
    {
        $results = AdminAlertRule::query()
            ->where('is_active', true)
            ->whereNotNull('metric')
            ->whereNotNull('operator')
            ->whereNotNull('value')
            ->whereNotNull('period')
            ->get()
            ->map(fn (AdminAlertRule $rule) => $this->evaluateRule($rule))
            ->values();

        return [
            'evaluated' => $results->count(),
            'triggered' => $results->where('triggered', true)->count(),
            'results' => $results,
        ];
    }

    public function evaluateRule(AdminAlertRule $rule): array
    {
        $actualValue = $this->metricValue($rule->metric, $rule->period);
        $triggered = $this->compare($actualValue, $rule->operator, (float) $rule->value);

        $result = [
            'uid' => $rule->uid,
            'nombre' => $rule->name,
            'metric' => $rule->metric,
            'operator' => $rule->operator,
            'value' => (float) $rule->value,
            'period' => $rule->period,
            'actual_value' => $actualValue,
            'triggered' => $triggered,
        ];

        if ($triggered) {
            $rule->forceFill(['last_triggered_at' => now()])->save();
            $this->notify($rule->fresh(), $actualValue);
            $result['last_triggered_at'] = optional($rule->fresh()->last_triggered_at)?->toISOString();
        }

        return $result;
    }

    private function metricValue(string $metric, string $period): float
    {
        $from = $this->periodStart($period);
        $logs = SystemLog::withoutGlobalScopes()
            ->where('created_at', '>=', $from)
            ->get();

        return match ($metric) {
            'errores' => (float) $logs->whereIn('level', ['critical', 'error'])->count(),
            'warnings' => (float) $logs->where('level', 'warning')->count(),
            'latencia' => (float) ($this->percentile(
                $logs
                    ->map(fn (SystemLog $log) => $this->extractLatencyMs($log))
                    ->filter(fn ($value) => $value !== null)
                    ->sort()
                    ->values(),
                95
            ) ?? 0),
            'uptime' => $this->uptime($logs),
            default => 0.0,
        };
    }

    private function periodStart(string $period): Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };
    }

    private function compare(float $actual, string $operator, float $expected): bool
    {
        return match ($operator) {
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }

    private function uptime($logs): float
    {
        $total = $logs->count();

        if ($total === 0) {
            return 100.0;
        }

        $errors = $logs->whereIn('level', ['critical', 'error'])->count();

        return round(max(0, 1 - ($errors / $total)) * 100, 2);
    }

    private function notify(AdminAlertRule $rule, float $actualValue): void
    {
        $payload = [
            'alert_uid' => $rule->uid,
            'alert_name' => $rule->name,
            'metric' => $rule->metric,
            'operator' => $rule->operator,
            'threshold' => (float) $rule->value,
            'period' => $rule->period,
            'actual_value' => $actualValue,
            'channels' => $rule->channels ?? [],
        ];

        LoggerService::log('alert', 'Admin alert triggered: ' . $rule->name, $payload);

        foreach ($rule->channels ?? [] as $channel) {
            match ($channel) {
                'EMAIL' => $this->notifyEmail($rule, $actualValue),
                'SLACK' => $this->notifySlack($rule, $actualValue),
                'PUSH' => $this->notifyPush($rule, $actualValue),
                default => null,
            };
        }
    }

    private function notifyEmail(AdminAlertRule $rule, float $actualValue): void
    {
        $to = config('services.admin_alerts.email') ?: config('mail.from.address');

        try {
            Mail::raw($this->notificationText($rule, $actualValue), function ($message) use ($rule, $to) {
                $message->to($to)->subject('Alerta administrativa: ' . $rule->name);
            });
        } catch (\Throwable $e) {
            Log::warning('Admin alert email notification failed', ['alert_uid' => $rule->uid, 'error' => $e->getMessage()]);
        }
    }

    private function notifySlack(AdminAlertRule $rule, float $actualValue): void
    {
        $webhookUrl = config('services.admin_alerts.slack_webhook_url');

        if (!$webhookUrl) {
            Log::info('Admin alert Slack notification skipped: missing webhook', ['alert_uid' => $rule->uid]);
            return;
        }

        try {
            Http::timeout(5)->post($webhookUrl, [
                'text' => $this->notificationText($rule, $actualValue),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin alert Slack notification failed', ['alert_uid' => $rule->uid, 'error' => $e->getMessage()]);
        }
    }

    private function notifyPush(AdminAlertRule $rule, float $actualValue): void
    {
        Log::notice('Admin alert push notification queued', [
            'alert_uid' => $rule->uid,
            'message' => $this->notificationText($rule, $actualValue),
        ]);
    }

    private function notificationText(AdminAlertRule $rule, float $actualValue): string
    {
        return sprintf(
            'La alerta "%s" se disparo: %s %s %.2f en %s. Valor actual: %.2f.',
            $rule->name,
            $rule->metric,
            $rule->operator,
            (float) $rule->value,
            $rule->period,
            $actualValue
        );
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
}
