<?php

namespace App\Models\Traits;

use App\Models\CustomFieldValue;

trait HasCustomFieldValues
{
    public function initializeHasCustomFieldValues(): void
    {
        $this->append('custom_fields');
        $this->mergeHidden(['customFieldValues', 'custom_field_values']);
    }

    public function customFieldValues()
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    public function getCustomFieldsAttribute(): array
    {
        if (!$this->relationLoaded('customFieldValues')) {
            return [];
        }

        return $this->customFieldValues
            ->map(function (CustomFieldValue $value) {
                $field = $value->relationLoaded('customField') ? $value->customField : null;

                return [
                    'custom_field_uid' => $value->custom_field_uid,
                    'key' => $field?->key,
                    'label' => $field?->label,
                    'type' => $field?->type,
                    'value' => $value->value,
                ];
            })
            ->values()
            ->all();
    }
}
