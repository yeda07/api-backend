<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasPublicUid, TenantScope, AppliesRowLevelSecurity, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'documentable_type',
        'documentable_id',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'uploaded_by_user_id',
        'documentable_id',
        'path',
    ];

    protected $appends = [
        'owner_user_uid',
        'uploaded_by_user_uid',
        'documentable_uid',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid;
    }

    public function getUploadedByUserUidAttribute()
    {
        return $this->uploadedBy?->uid;
    }

    public function getDocumentableUidAttribute()
    {
        return $this->documentable?->uid;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}
