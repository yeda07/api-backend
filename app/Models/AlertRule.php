<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'document_type_id',
        'days_before',
        'notification_channel',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'document_type_id',
    ];

    protected $appends = [
        'document_type_uid',
    ];

    protected $casts = [
        'days_before' => 'integer',
        'is_active' => 'boolean',
    ];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function alerts()
    {
        return $this->hasMany(DocumentAlert::class);
    }

    public function getDocumentTypeUidAttribute()
    {
        return $this->documentType?->uid;
    }
}
