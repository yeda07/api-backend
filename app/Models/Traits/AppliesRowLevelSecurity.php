<?php

namespace App\Models\Traits;

use App\Services\RowLevelSecurityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait AppliesRowLevelSecurity
{
    protected static function bootAppliesRowLevelSecurity()
    {
        static::addGlobalScope('row_level_security', function (Builder $builder) {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();
            $service = app(RowLevelSecurityService::class);

            if ($service->shouldBypassScope($user)) {
                return;
            }

            $visibleUserIds = $service->visibleUserIds($user);
            $model = $builder->getModel();
            $table = $model->getTable();

            $builder->where(function (Builder $query) use ($visibleUserIds, $table, $model) {
                $query->whereIn($table . '.owner_user_id', $visibleUserIds);

                if (method_exists($model, 'applyInheritedRowLevelSecurity')) {
                    $model->applyInheritedRowLevelSecurity($query, $visibleUserIds, $table);
                }
            });
        });

        static::creating(function ($model) {
            if (!Auth::check() || !empty($model->owner_user_id)) {
                return;
            }

            if (method_exists($model, 'resolveDefaultOwnerUserId')) {
                $model->owner_user_id = $model->resolveDefaultOwnerUserId();

                return;
            }

            $model->owner_user_id = Auth::id();
        });
    }
}
