<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope, AppliesRowLevelSecurity, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'document_type_id',
        'owner_user_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'issue_date',
        'expiration_date',
        'status',
        'is_active',
        'version_number',
        'replaced_at',
        'documentable_type',
        'documentable_id',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
        'document_type_id',
        'owner_user_id',
        'uploaded_by_user_id',
        'documentable_id',
        'path',
    ];

    protected $appends = [
        'owner_user_uid',
        'uploaded_by_user_uid',
        'documentable_uid',
        'account_uid',
        'document_type_uid',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'is_active' => 'boolean',
        'replaced_at' => 'datetime',
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

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function alerts()
    {
        return $this->hasMany(DocumentAlert::class)->latest('alert_date');
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

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getDocumentTypeUidAttribute()
    {
        return $this->documentType?->uid;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}
