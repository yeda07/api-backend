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
        'debug'
    ];

    public static function log($level, $message, $context = [])
    {
        //  Validar nivel
        if (!in_array($level, self::$allowedLevels)) {
            $level = 'info';
        }

        $tenantId = auth()->user()?->tenant_id ?? null;

        // PROTECCIÓN (evita loop infinito si falla DB)
        try {
            SystemLog::create([
                'tenant_id' => $tenantId,
                'level'     => $level,
                'message'   => $message,
                'context'   => $context
            ]);
        } catch (\Throwable $e) {
            // no hacer nada (evita crash total)
        }

        // Log Laravel (siempre seguro)
        Log::log($level, $message, $context);

        // ALERTAS SOLO CRÍTICAS
        if (in_array($level, ['error', 'critical'])) {
            self::notify($message, $context);
        }
    }

    private static function notify($message, $context)
    {
        Log::alert('ALERTA CRÍTICA', [
            'message' => $message,
            'context' => $context
        ]);
    }
}
