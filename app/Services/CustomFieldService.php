<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    public function createField(array $data)
    {
        $data['entity_type'] = $this->resolveEntityType($data['entity_type'] ?? '');

        return CustomField::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
        ]);
    }

    public function assignValue(string $entityType, string $entityUid, string $fieldUid, mixed $value)
    {
        $tenantId = auth()->user()->tenant_id;
        $resolvedType = $this->resolveEntityType($entityType);

        $field = CustomField::where('tenant_id', $tenantId)
            ->where('uid', $fieldUid)
            ->where('entity_type', $resolvedType)
            ->first();

        if (!$field) {
            throw ValidationException::withMessages([
                'field' => ['El campo no existe o no pertenece a esta entidad'],
            ]);
        }

        $entityId = find_entity_id_by_uid($resolvedType, $entityUid);

        if (!$entityId) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad no existe'],
            ]);
        }

        $value = $this->validateValue($field, $value);

        return CustomFieldValue::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'entity_type' => $resolvedType,
                'entity_id' => $entityId,
                'custom_field_id' => $field->id,
            ],
            [
                'value' => $value,
            ]
        );
    }

    private function resolveEntityType(string $entityType): string
    {
        $resolvedType = crm_entity_model_class($entityType);

        if (!$resolvedType) {
            throw ValidationException::withMessages([
                'entity_type' => ['Tipo de entidad no soportado'],
            ]);
        }

        return $resolvedType;
    }

    private function validateValue($field, $value)
    {
        $rules = $field->options ?? [];

        if (($rules['required'] ?? false) && ($value === null || $value === '')) {
            throw ValidationException::withMessages([
                'value' => ["El campo '{$field->name}' es obligatorio"],
            ]);
        }

        if ($value === null || $value === '') {
            return null;
        }

        switch ($field->type) {
            case 'number':
                if (!is_numeric($value)) {
                    throw ValidationException::withMessages([
                        'value' => ["El campo '{$field->name}' debe ser numerico"],
                    ]);
                }

                $value = (float) $value;

                if (isset($rules['min']) && $value < $rules['min']) {
                    throw ValidationException::withMessages([
                        'value' => ["El valor minimo para '{$field->name}' es {$rules['min']}"],
                    ]);
                }

                if (isset($rules['max']) && $value > $rules['max']) {
                    throw ValidationException::withMessages([
                        'value' => ["El valor maximo para '{$field->name}' es {$rules['max']}"],
                    ]);
                }

                return $value;

            case 'text':
                if (!is_string($value)) {
                    throw ValidationException::withMessages([
                        'value' => ["El campo '{$field->name}' debe ser texto"],
                    ]);
                }

                $value = trim($value);

                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    throw ValidationException::withMessages([
                        'value' => ["Maximo {$rules['max_length']} caracteres"],
                    ]);
                }

                return $value;

            case 'date':
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    throw ValidationException::withMessages([
                        'value' => ["El campo '{$field->name}' debe ser una fecha valida"],
                    ]);
                }

            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    throw ValidationException::withMessages([
                        'value' => ["El campo '{$field->name}' debe ser booleano"],
                    ]);
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'select':
                $options = $rules['values'] ?? [];

                if (!in_array($value, $options, true)) {
                    throw ValidationException::withMessages([
                        'value' => ["Valor no permitido para '{$field->name}'"],
                    ]);
                }

                return $value;

            default:
                throw ValidationException::withMessages([
                    'type' => ['Tipo de campo no soportado'],
                ]);
        }
    }
}
