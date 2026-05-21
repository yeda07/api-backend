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
        $user = auth()->user();

        if (!$user) {
            return;
        }

        $relationTable = $builder->getModel()->getTable();
        $visibleUserIds = $this->visibleUserIds($user);
        $bypassScope = $this->shouldBypassScope($user);

        $builder->where(function (Builder $relationQuery) use ($relationTable, $user, $visibleUserIds, $bypassScope) {
            $this->addVisibleRelationSideScope($relationQuery, 'from_type', 'from_id', $relationTable, $user, $visibleUserIds, $bypassScope);
        });

        $builder->where(function (Builder $relationQuery) use ($relationTable, $user, $visibleUserIds, $bypassScope) {
            $this->addVisibleRelationSideScope($relationQuery, 'to_type', 'to_id', $relationTable, $user, $visibleUserIds, $bypassScope);
        });
    }

    private function addVisibleRelationSideScope(
        Builder $relationQuery,
        string $typeColumn,
        string $idColumn,
        string $relationTable,
        User $user,
        array $visibleUserIds,
        bool $bypassScope
    ): void {
        foreach ($this->relationEntityTables() as $type => $table) {
            $relationQuery->orWhere(function (Builder $typedQuery) use ($type, $table, $typeColumn, $idColumn, $relationTable, $user, $visibleUserIds, $bypassScope) {
                $typedQuery
                    ->where($relationTable.'.'.$typeColumn, $type)
                    ->whereExists(function ($entityQuery) use ($type, $table, $idColumn, $relationTable, $user, $visibleUserIds, $bypassScope) {
                        $entityQuery
                            ->selectRaw('1')
                            ->from($table)
                            ->whereColumn($table.'.id', $relationTable.'.'.$idColumn)
                            ->where($table.'.tenant_id', $user->tenant_id);

                        $this->applyRelationEntityVisibility($entityQuery, $type, $table, $visibleUserIds, $bypassScope);
                    });
            });
        }
    }

    private function applyRelationEntityVisibility($query, string $type, string $table, array $visibleUserIds, bool $bypassScope): void
    {
        if ($bypassScope) {
            return;
        }

        if (empty($visibleUserIds)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($type === Contact::class) {
            $query->where(function ($contactQuery) use ($table, $visibleUserIds) {
                $contactQuery
                    ->whereIn($table.'.owner_user_id', $visibleUserIds)
                    ->orWhere(function ($inheritedQuery) use ($table, $visibleUserIds) {
                        $inheritedQuery
                            ->whereNull($table.'.owner_user_id')
                            ->whereExists(function ($accountQuery) use ($table, $visibleUserIds) {
                                $accountQuery
                                    ->selectRaw('1')
                                    ->from('accounts')
                                    ->whereColumn('accounts.id', $table.'.account_id')
                                    ->whereIn('accounts.owner_user_id', $visibleUserIds);
                            });
                    });
            });

            return;
        }

        $query->whereIn($table.'.owner_user_id', $visibleUserIds);
    }

    /**
     * @return array<class-string, string>
     */
    private function relationEntityTables(): array
    {
        return [
            Account::class => 'accounts',
            Contact::class => 'contacts',
            CrmEntity::class => 'crm_entities',
        ];
    }
}
