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
}
