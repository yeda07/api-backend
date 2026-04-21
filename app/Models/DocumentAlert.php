<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class DocumentAlert extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'document_id',
        'alert_rule_id',
        'alert_date',
        'notification_channel',
        'status',
        'message',
        'read_at',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'document_id',
        'alert_rule_id',
    ];

    protected $appends = [
        'document_uid',
        'alert_rule_uid',
    ];

    protected $casts = [
        'alert_date' => 'date',
        'read_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function alertRule()
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function getDocumentUidAttribute()
    {
        return $this->document?->uid;
    }

    public function getAlertRuleUidAttribute()
    {
        return $this->alertRule?->uid;
    }
}
