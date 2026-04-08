<?php
namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use Illuminate\Validation\ValidationException;

class PlanLimitService
{
    public static function check(string $resource)
    {
        $tenant = auth()->user()->tenant;
        $plan   = $tenant->plan;

        if (!$plan) return; // sin plan = sin límites

        match ($resource) {
            'accounts' => self::checkAccounts($tenant->id, $plan->max_accounts),
            'contacts' => self::checkContacts($tenant->id, $plan->max_contacts),
            'entities' => self::checkEntities($tenant->id, $plan->max_entities),
            default => null
        };
    }

    private static function checkAccounts($tenantId, $limit)
    {
        if (!$limit) return;

        $count = Account::where('tenant_id', $tenantId)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'plan' => ['Has alcanzado el límite de cuentas de tu plan']
            ]);
        }
    }

    private static function checkContacts($tenantId, $limit)
    {
        if (!$limit) return;

        $count = Contact::where('tenant_id', $tenantId)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'plan' => ['Has alcanzado el límite de contactos de tu plan']
            ]);
        }
    }

    private static function checkEntities($tenantId, $limit)
    {
        if (!$limit) return;

        $count = CrmEntity::where('tenant_id', $tenantId)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'plan' => ['Has alcanzado el límite de entidades del plan']
            ]);
        }
    }
}
