<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'description',
        'trigger_source',
        'trigger_event',
        'trigger_config',
        'conditions',
        'actions',
        'logic',
        'is_active',
        'execution_count',
        'last_executed_at',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'execution_count' => 'integer',
        'last_executed_at' => 'datetime',
    ];
}
