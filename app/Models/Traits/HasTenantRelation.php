<?php

namespace App\Models\Traits;

use App\Models\Tenant;

trait HasTenantRelation
{
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
