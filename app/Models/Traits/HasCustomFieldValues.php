<?php

namespace App\Models\Traits;

use App\Models\CustomFieldValue;

trait HasCustomFieldValues
{
    public function customFieldValues()
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }
}
