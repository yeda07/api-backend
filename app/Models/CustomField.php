<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasPublicUid;

class CustomField extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'tenant_id',
        'entity_type',
        'name',
        'key',
        'type',
        'options'
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'options' => 'array'
    ];

    protected $appends = [
        'entity_type_key',
        'label',
        'module',
        'required',
        'select_options',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | CONSTANTES (TIPOS DE CAMPOS)
    |--------------------------------------------------------------------------
    */

    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_SELECT = 'select';
    const TYPE_DATE = 'date';
    const TYPE_BOOLEAN = 'boolean';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isSelect()
    {
        return $this->type === self::TYPE_SELECT;
    }

    public function hasOptions()
    {
        return !empty($this->options);
    }

    public function getLabelAttribute()
    {
        return $this->name;
    }

    public function getEntityTypeKeyAttribute(): string
    {
        return match ($this->entity_type) {
            Account::class => 'company',
            Contact::class => 'contact',
            CrmEntity::class => 'crm_entity',
            Opportunity::class => 'opportunity',
            Product::class => 'product',
            default => $this->entity_type,
        };
    }

    public function getModuleAttribute()
    {
        if (!empty($this->options['_module'])) {
            return $this->options['_module'];
        }

        return match ($this->entity_type) {
            Account::class => 'companies',
            Contact::class => 'contacts',
            CrmEntity::class => 'opportunities',
            Opportunity::class => 'opportunities',
            Product::class => 'products',
            default => $this->entity_type,
        };
    }

    public function getRequiredAttribute(): bool
    {
        return (bool) ($this->options['required'] ?? false);
    }

    public function getSelectOptionsAttribute(): ?array
    {
        return $this->type === self::TYPE_SELECT
            ? ($this->options['values'] ?? $this->options['options'] ?? null)
            : null;
    }
}
