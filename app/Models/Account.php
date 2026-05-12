<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTags;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use AppliesRowLevelSecurity, HasCustomFieldValues, HasPublicUid, HasTags, HasUserTimezone, TenantScope;

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
        'address',
        'status',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'tax_id',
        'status',
        'country',
        'city',
        'company_size',
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

    public function projects()
    {
        return $this->hasMany(Project::class);
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

    public function getTaxIdAttribute()
    {
        return $this->document;
    }

    public function getStatusAttribute()
    {
        return $this->attributes['status'] ?? 'active';
    }

    public function getCountryAttribute()
    {
        return null;
    }

    public function getCityAttribute()
    {
        return null;
    }

    public function getCompanySizeAttribute()
    {
        return null;
    }

    public function getDisplayNameAttribute()
    {
        return $this->name;
    }
}
