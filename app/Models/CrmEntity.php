<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTags;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CrmEntity extends Model
{
    use HasPublicUid, TenantScope, AppliesRowLevelSecurity, HasTags, HasCustomFieldValues, HasUserTimezone;

    protected $table = 'crm_entities';

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'type',
        'profile_data',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
    ];

    protected $appends = [
        'owner_user_uid',
    ];

    protected $casts = [
        'profile_data' => 'array',
    ];

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
        return match ($this->type) {
            'B2B' => $this->profile_data['company_name'] ?? 'Empresa',
            'B2C' => ($this->profile_data['first_name'] ?? '') . ' ' . ($this->profile_data['last_name'] ?? ''),
            'B2G' => $this->profile_data['institution_name'] ?? 'Institucion',
            default => 'Entidad',
        };
    }
}
