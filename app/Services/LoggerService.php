<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Log;

class LoggerService
{
    protected static $allowedLevels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    public static function log($level, $message, $context = [])
    {
        if (!in_array($level, self::$allowedLevels, true)) {
            $level = 'info';
        }

        $tenantId = auth()->user()?->tenant_id ?? null;

        try {
            SystemLog::create([
                'tenant_id' => $tenantId,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            // Best effort only: never break the request because system logging failed.
        }

        try {
            Log::log($level, $message, $context);
        } catch (\Throwable $e) {
            // File/stream logging can fail in restricted environments; ignore it safely.
        }

        if (in_array($level, ['error', 'critical'], true)) {
            try {
                self::notify($message, $context);
            } catch (\Throwable $e) {
                // Notifications are optional and must not affect application behavior.
            }
        }
    }

    private static function notify($message, $context)
    {
        Log::alert('ALERTA CRITICA', [
            'message' => $message,
            'context' => $context,
        ]);
    }
}
