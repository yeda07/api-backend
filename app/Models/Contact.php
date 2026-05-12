<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTags;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use AppliesRowLevelSecurity, HasCustomFieldValues, HasPublicUid, HasTags, HasUserTimezone, TenantScope;

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
        'status',
        'is_public_entity',
    ];

    protected $casts = [
        'is_public_entity' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'account_id',
    ];

    protected $appends = [
        'account_uid',
        'company_uid',
        'company_name',
        'owner_user_uid',
        'name',
        'job_title',
        'type',
        'status',
        'id_number',
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
        return $this->account?->uid
            ?? ($this->account_id ? Account::query()->whereKey($this->account_id)->value('uid') : null);
    }

    public function getCompanyUidAttribute()
    {
        return $this->account_uid;
    }

    public function getCompanyNameAttribute()
    {
        return $this->account?->name
            ?? ($this->account_id ? Account::query()->whereKey($this->account_id)->value('name') : null);
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
            $nestedQuery->whereNull($table.'.owner_user_id')
                ->whereHas('account', fn ($accountQuery) => $accountQuery->whereIn('owner_user_id', $visibleUserIds));
        });
    }

    //  Helper
    public function getDisplayNameAttribute()
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getNameAttribute()
    {
        return $this->display_name;
    }

    public function getJobTitleAttribute()
    {
        return $this->position;
    }

    public function getTypeAttribute()
    {
        return $this->is_public_entity ? 'government' : 'person';
    }

    public function getStatusAttribute()
    {
        return $this->attributes['status'] ?? 'active';
    }

    public function getIdNumberAttribute()
    {
        return null;
    }
}
