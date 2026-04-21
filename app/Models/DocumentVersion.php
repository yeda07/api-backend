<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'document_id',
        'created_by_user_id',
        'version_number',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'issue_date',
        'expiration_date',
        'status',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'document_id',
        'created_by_user_id',
        'file_path',
    ];

    protected $appends = [
        'document_uid',
        'created_by_user_uid',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getDocumentUidAttribute()
    {
        return $this->document?->uid;
    }

    public function getCreatedByUserUidAttribute()
    {
        return $this->createdBy?->uid;
    }
}
