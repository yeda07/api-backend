<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\HasTags;
use App\Models\Traits\TenantScope;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasUserTimezone;


class Contact extends Model
{
    use HasPublicUid, TenantScope, AppliesRowLevelSecurity, HasTags, HasCustomFieldValues, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'account_id',
    ];

    protected $appends = [
        'account_uid',
        'owner_user_uid',
    ];

    // Relación con Account
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // s Relación con Tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid ?? $this->account?->owner?->uid;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        if ($this->account_id) {
            return Account::query()
                ->withoutGlobalScopes()
                ->whereKey($this->account_id)
                ->value('owner_user_id') ?? auth()->id();
        }

        return auth()->id();
    }

    public function applyInheritedRowLevelSecurity($query, array $visibleUserIds, string $table): void
    {
        $query->orWhere(function ($nestedQuery) use ($visibleUserIds, $table) {
            $nestedQuery->whereNull($table . '.owner_user_id')
                ->whereHas('account', fn ($accountQuery) => $accountQuery->whereIn('owner_user_id', $visibleUserIds));
        });
    }

    //  Helper
    public function getDisplayNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
