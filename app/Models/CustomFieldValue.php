<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'tenant_id',
        'entity_type',
        'entity_id',
        'custom_field_id',
        'value',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'entity_id',
        'custom_field_id',
    ];

    protected $appends = [
        'entity_uid',
        'custom_field_uid',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class);
    }

    public function entity()
    {
        return $this->morphTo(null, 'entity_type', 'entity_id');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEntity($query, $type, $id)
    {
        return $query
            ->where('entity_type', $type)
            ->where('entity_id', $id);
    }

    public function isForEntity($type, $id)
    {
        return $this->entity_type === $type && $this->entity_id === $id;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getEntityUidAttribute()
    {
        return match ($this->entity_type) {
            Account::class => Account::find($this->entity_id)?->uid,
            Contact::class => Contact::find($this->entity_id)?->uid,
            CrmEntity::class => CrmEntity::find($this->entity_id)?->uid,
            default => null,
        };
    }

    public function getCustomFieldUidAttribute()
    {
        return $this->customField?->uid;
    }
}
