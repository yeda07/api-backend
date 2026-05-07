<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Battlecard extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

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
        'competitor_name',
        'description',
        'strengths',
        'weaknesses',
        'objections',
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

    public function getCompetitorNameAttribute(): ?string
    {
        return $this->competitor?->name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->summary;
    }

    public function getStrengthsAttribute(): array
    {
        return $this->differentiators ?? [];
    }

    public function getWeaknessesAttribute(): array
    {
        return $this->recommended_actions ?? [];
    }

    public function getObjectionsAttribute(): array
    {
        return collect($this->objection_handlers ?? [])
            ->map(function ($handler) {
                if (!is_array($handler)) {
                    return $handler;
                }

                return [
                    'objection' => $handler['objection'] ?? $handler['question'] ?? $handler['title'] ?? null,
                    'response' => $handler['response'] ?? $handler['answer'] ?? $handler['handling'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
