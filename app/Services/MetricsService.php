<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Relation;
use App\Services\UsageAlertService; // 🔥 NUEVO

class MetricsService
{
    public static function getTenantMetrics($tenantId)
    {
        return [
            'accounts' => Account::where('tenant_id', $tenantId)->count(),
            'contacts' => Contact::where('tenant_id', $tenantId)->count(),
            'entities' => CrmEntity::where('tenant_id', $tenantId)->count(),
            'relations' => Relation::where('tenant_id', $tenantId)->count(),
        ];
    }

    public static function getTenantUsageWithLimits($tenant)
    {
        $metrics = self::getTenantMetrics($tenant->id);
        $plan = $tenant->plan;

        // ✅ LIMITS (igual que tenías)
        $limits = [
            'accounts' => $plan->max_accounts,
            'contacts' => $plan->max_contacts,
            'entities' => $plan->max_entities,
        ];

        // ✅ PERCENTAGE (igual que tenías)
        $percentage = [
            'accounts' => self::percent($metrics['accounts'], $plan->max_accounts),
            'contacts' => self::percent($metrics['contacts'], $plan->max_contacts),
            'entities' => self::percent($metrics['entities'], $plan->max_entities),
        ];

        // 🔥 NUEVO: ALERTAS (NO rompe nada)
        $alerts = UsageAlertService::check($percentage);

        return [
            'usage' => $metrics,
            'limits' => $limits,
            'percentage' => $percentage,
            'alerts' => $alerts, // 🔥 NUEVO
        ];
    }

    private static function percent($used, $limit)
    {
        if (!$limit) return null; // ilimitado
        return round(($used / $limit) * 100, 2);
    }
}
