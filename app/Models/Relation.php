<?php

namespace App\Models;

use App\Services\RowLevelSecurityService;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use App\Models\Traits\HasUserTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Relation extends Model
{
    use HasPublicUid, TenantScope, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'relation_type'
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'from_id',
        'to_id',
    ];

    protected $appends = [
        'from_uid',
        'to_uid',
    ];

    // Polimórficas

    public function from()
    {
        return $this->morphTo(null, 'from_type', 'from_id');
    }

    public function to()
    {
        return $this->morphTo(null, 'to_type', 'to_id');
    }

    // 🔗 Tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getFromUidAttribute()
    {
        return $this->from?->uid;
    }

    public function getToUidAttribute()
    {
        return $this->to?->uid;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('relation_row_level_security', function (Builder $builder) {
            if (!Auth::check()) {
                return;
            }

            app(RowLevelSecurityService::class)->applyRelationScope($builder);
        });
    }
}
