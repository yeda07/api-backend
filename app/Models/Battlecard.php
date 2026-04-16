<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Battlecard extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'competitor_id',
        'title',
        'summary',
        'differentiators',
        'objection_handlers',
        'recommended_actions',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'competitor_id',
    ];

    protected $appends = [
        'competitor_uid',
    ];

    protected $casts = [
        'differentiators' => 'array',
        'objection_handlers' => 'array',
        'recommended_actions' => 'array',
        'is_active' => 'boolean',
    ];

    public function competitor()
    {
        return $this->belongsTo(Competitor::class);
    }

    public function getCompetitorUidAttribute(): ?string
    {
        return $this->competitor?->uid;
    }
}
