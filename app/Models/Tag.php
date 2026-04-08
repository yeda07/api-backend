<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'key',
        'color',
        'category',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    public function accounts()
    {
        return $this->morphedByMany(Account::class, 'taggable')->withTimestamps();
    }

    public function contacts()
    {
        return $this->morphedByMany(Contact::class, 'taggable')->withTimestamps();
    }

    public function crmEntities()
    {
        return $this->morphedByMany(CrmEntity::class, 'taggable')->withTimestamps();
    }
}
