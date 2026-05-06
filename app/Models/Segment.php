<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'description',
        'entity_type',
        'rules',
        'logic',
        'execution_count',
        'last_run_at',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'rules' => 'array',
        'execution_count' => 'integer',
        'last_run_at' => 'datetime',
    ];
}
