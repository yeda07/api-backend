<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Support\ApiIndex;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    public function listFields(array $filters = [])
    {
        $tenantId = auth()->user()->tenant_id;
        $query = CustomField::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('entity_type')
            ->orderBy('name');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $this->resolveEntityType($filters['entity_type']));
        }

        return ApiIndex::paginateOrGet($query, $filters, 'custom_fields_page');
    }

    public function createField(array $data)
    {
        $data = $this->validateFieldPayload($data);
        $data['entity_type'] = $this->resolveEntityType($data['entity_type']);

        return CustomField::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
        ]);
    }

    public function updateField(string $uid, array $data): CustomField
    {
        $field = $this->findField($uid);
        $validated = $this->validateFieldPayload($data, true);

        if (array_key_exists('entity_type', $validated)) {
            $validated['entity_type'] = $this->resolveEntityType($validated['entity_type']);
        }

        $field->update($validated);

        return $field->fresh();
    }

    public function deleteField(string $uid): void
    {
        $field = $this->findField($uid);

        CustomFieldValue::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('custom_field_id', $field->getKey())
            ->delete();

        $field->delete();
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
        $entityType = match ($entityType) {
            'companies', 'company' => 'accounts',
            'opportunities', 'opportunity', 'products', 'product' => 'crm_entities',
            default => $entityType,
        };

        $resolvedType = crm_entity_model_class($entityType);

        if (!$resolvedType) {
            throw ValidationException::withMessages([
                'entity_type' => ['Tipo de entidad no soportado'],
            ]);
        }

        return $resolvedType;
    }

    private function findField(string $uid): CustomField
    {
        return CustomField::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('uid', $uid)
            ->firstOrFail();
    }

    private function validateFieldPayload(array $data, bool $partial = false): array
    {
        $validated = Validator::make($data, [
            'entity_type' => [$partial ? 'sometimes' : 'required_without:module', 'string'],
            'module' => [$partial ? 'sometimes' : 'nullable', 'string'],
            'name' => [$partial ? 'sometimes' : 'required_without:label', 'string', 'max:255'],
            'label' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:text,number,select,date,boolean'],
            'required' => 'sometimes|boolean',
            'options' => 'nullable|array',
        ])->validate();

        if (!empty($validated['module']) && empty($validated['entity_type'])) {
            $validated['entity_type'] = $validated['module'];
        }

        if (!empty($validated['label'])) {
            $validated['name'] = $validated['label'];
        }

        unset($validated['module'], $validated['label']);

        if (array_key_exists('required', $validated)) {
            $options = $validated['options'] ?? [];
            $options['required'] = (bool) $validated['required'];
            $validated['options'] = $options;
            unset($validated['required']);
        }

        return $validated;
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
