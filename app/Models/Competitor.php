<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Competitor extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'key',
        'website',
        'strengths',
        'weaknesses',
        'notes',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $appends = [
        'description',
        'strength_score',
    ];

    protected $casts = [
        'strengths' => 'array',
        'weaknesses' => 'array',
        'is_active' => 'boolean',
    ];

    public function battlecards()
    {
        return $this->hasMany(Battlecard::class);
    }

    public function lostReasons()
    {
        return $this->hasMany(LostReason::class);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->notes;
    }

    public function getStrengthScoreAttribute(): int
    {
        return min(10, max(0, count($this->strengths ?? []) * 2));
    }
}
