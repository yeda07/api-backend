<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\HasTags;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use App\Models\Traits\HasUserTimezone;

class Account extends Model
{
    use HasPublicUid, TenantScope, AppliesRowLevelSecurity, HasTags, HasCustomFieldValues, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'name',
        'document',
        'email',
        'industry',
        'website',
        'phone',
        'address'
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
    ];

    protected $appends = [
        'owner_user_uid',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function products()
    {
        return $this->hasMany(AccountProduct::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid;
    }

    public function getDisplayNameAttribute()
    {
        return $this->name;
    }
}
