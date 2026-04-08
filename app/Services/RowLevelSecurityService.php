<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RowLevelSecurityService
{
    public function shouldBypassScope(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function visibleUserIds(User $user): array
    {
        $visibleIds = [$user->getKey()];
        $pendingIds = [$user->getKey()];

        while (!empty($pendingIds)) {
            $subordinates = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('manager_id', $pendingIds)
                ->pluck('id')
                ->all();

            $newIds = array_values(array_diff($subordinates, $visibleIds));

            if (empty($newIds)) {
                break;
            }

            $visibleIds = array_merge($visibleIds, $newIds);
            $pendingIds = $newIds;
        }

        return array_values(array_unique($visibleIds));
    }

    public function visibleEntityIds(string $modelClass): array
    {
        if (!in_array($modelClass, [Account::class, Contact::class, CrmEntity::class], true)) {
            return [];
        }

        return $modelClass::query()->pluck('id')->all();
    }

    public function applyRelationScope(Builder $builder): void
    {
        $supportedTypes = [
            Account::class,
            Contact::class,
            CrmEntity::class,
        ];

        $visibleIdsByType = [];

        foreach ($supportedTypes as $type) {
            $visibleIdsByType[$type] = $this->visibleEntityIds($type);
        }

        $builder->where(function (Builder $relationQuery) use ($supportedTypes, $visibleIdsByType) {
            foreach ($supportedTypes as $type) {
                $relationQuery->orWhere(function (Builder $typedQuery) use ($type, $visibleIdsByType) {
                    $fromVisibleIds = $visibleIdsByType[$type];

                    $typedQuery->where('from_type', $type);

                    if (empty($fromVisibleIds)) {
                        $typedQuery->whereRaw('1 = 0');
                    } else {
                        $typedQuery->whereIn('from_id', $fromVisibleIds);
                    }
                });
            }
        });

        $builder->where(function (Builder $relationQuery) use ($supportedTypes, $visibleIdsByType) {
            foreach ($supportedTypes as $type) {
                $relationQuery->orWhere(function (Builder $typedQuery) use ($type, $visibleIdsByType) {
                    $toVisibleIds = $visibleIdsByType[$type];

                    $typedQuery->where('to_type', $type);

                    if (empty($toVisibleIds)) {
                        $typedQuery->whereRaw('1 = 0');
                    } else {
                        $typedQuery->whereIn('to_id', $toVisibleIds);
                    }
                });
            }
        });
    }
}
