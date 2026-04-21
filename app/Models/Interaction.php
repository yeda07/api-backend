<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'actor_user_id',
        'type',
        'subject',
        'content',
        'meta',
        'occurred_at',
        'interactable_type',
        'interactable_id',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'actor_user_id',
        'interactable_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'actor_user_uid',
        'interactable_uid',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function interactable()
    {
        return $this->morphTo();
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid;
    }

    public function getActorUserUidAttribute()
    {
        return $this->actor?->uid;
    }

    public function getInteractableUidAttribute()
    {
        return $this->interactable?->uid;
    }
}
