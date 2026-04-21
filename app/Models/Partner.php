<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'name',
        'type',
        'status',
        'contact_info',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
    ];

    protected $appends = [
        'account_uid',
    ];

    protected $casts = [
        'contact_info' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunities()
    {
        return $this->hasMany(PartnerOpportunity::class);
    }

    public function resources()
    {
        return $this->belongsToMany(PartnerResource::class, 'partner_access')->withTimestamps();
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }
}
