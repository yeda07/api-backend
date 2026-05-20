<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Opportunity;
use App\Models\Product;
use App\Support\ApiIndex;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    private const SUPPORTED_MODULES = [
        'contacts' => 'Contactos',
        'companies' => 'Empresas',
        'opportunities' => 'Oportunidades',
        'products' => 'Productos',
    ];

    public function modules(): array
    {
        return collect(self::SUPPORTED_MODULES)
            ->filter(fn (string $label, string $value) => crm_entity_model_class($this->normalizeEntityType($value)) !== null)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public function listFields(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'nullable|string',
            'module' => 'nullable|string',
            'search' => 'nullable|string|max:255',
        ])->validate();

        $tenantId = auth()->user()->tenant_id;
        $query = CustomField::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('entity_type')
            ->orderBy('name');

        $module = $this->resolveModuleFilter($validated);

        if ($module) {
            $this->applyModuleFilter($query, $module);
        } elseif (!empty($validated['entity_type'])) {
            $query->where('entity_type', $this->resolveEntityType($validated['entity_type']));
        }

        if (!empty($validated['search'])) {
            $search = '%' . mb_strtolower($validated['search']) . '%';

            $query->whereRaw('LOWER(name) LIKE ?', [$search]);
        }

        return [
            'items' => ApiIndex::paginateOrGet($query, $filters, 'custom_fields_page'),
            'totals' => $this->moduleTotals($tenantId),
        ];
    }

    public function createField(array $data)
    {
        $data = $this->validateFieldPayload($data);
        $data['entity_type'] = $this->resolveEntityType($data['entity_type']);
        $data['key'] = $this->uniqueFieldKey(
            $data['entity_type'],
            $data['key'] ?? $data['name']
        );

        return CustomField::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
        ]);
    }

    public function serializeField(CustomField $field): array
    {
        return [
            'uid' => $field->uid,
            'entity_type' => $field->entity_type_key,
            'name' => $field->name,
            'label' => $field->label,
            'key' => $field->key,
            'type' => $field->type,
            'module' => $field->module,
            'required' => $field->required,
            'options' => $field->options,
            'select_options' => $field->select_options,
            'created_at' => $field->created_at,
            'updated_at' => $field->updated_at,
        ];
    }

    public function serializeFields($fields)
    {
        if ($fields instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return $fields->through(fn (CustomField $field) => $this->serializeField($field));
        }

        return $fields->map(fn (CustomField $field) => $this->serializeField($field));
    }

    public function updateField(string $uid, array $data): CustomField
    {
        $field = $this->findField($uid);
        $validated = $this->validateFieldPayload($data, true);

        if (array_key_exists('entity_type', $validated)) {
            $validated['entity_type'] = $this->resolveEntityType($validated['entity_type']);
        }

        if (array_key_exists('key', $validated) && $validated['key'] === null) {
            unset($validated['key']);
        }

        if (array_key_exists('key', $validated)) {
            $validated['key'] = $this->uniqueFieldKey(
                $validated['entity_type'] ?? $field->entity_type,
                $validated['key'],
                $field->getKey()
            );
        } elseif (array_key_exists('entity_type', $validated)) {
            $validated['key'] = $this->uniqueFieldKey(
                $validated['entity_type'],
                $field->key,
                $field->getKey()
            );
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
        $entityType = $this->normalizeEntityType($entityType);

        $resolvedType = crm_entity_model_class($entityType);

        if (!$resolvedType) {
            throw ValidationException::withMessages([
                'entity_type' => ['Tipo de entidad no soportado'],
            ]);
        }

        return $resolvedType;
    }

    private function normalizeEntityType(string $entityType): string
    {
        return match ($entityType) {
            'companies', 'company' => 'accounts',
            default => $entityType,
        };
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
            'key' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:text,number,select,date,boolean'],
            'required' => 'sometimes|boolean',
            'options' => 'nullable|array',
        ])->validate();

        if (!empty($validated['module']) && empty($validated['entity_type'])) {
            $validated['module'] = $this->normalizeModule($validated['module']);
            $validated['entity_type'] = $validated['module'];
        } elseif (!empty($validated['module'])) {
            $validated['module'] = $this->normalizeModule($validated['module']);
        } elseif (!empty($validated['entity_type'])) {
            $module = $this->moduleFromInput($validated['entity_type']);

            if ($module) {
                $validated['module'] = $module;
            }
        }

        if (!empty($validated['module'])) {
            $options = $validated['options'] ?? [];
            $options['_module'] = $validated['module'];
            $validated['options'] = $options;
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

    private function uniqueFieldKey(string $entityType, string $source, ?int $ignoreId = null): string
    {
        $base = $this->normalizeFieldKey($source);
        $key = $base;
        $suffix = 2;

        while (
            CustomField::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('entity_type', $entityType)
                ->where('key', $key)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $key = Str::limit($base, 250 - strlen((string) $suffix), '') . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }

    private function normalizeFieldKey(string $source): string
    {
        $key = str_replace('-', '_', Str::slug($source, '_'));

        return $key !== '' ? $key : 'custom_field';
    }

    private function resolveModuleFilter(array $validated): ?string
    {
        if (!empty($validated['module'])) {
            return $this->normalizeModule($validated['module']);
        }

        return !empty($validated['entity_type'])
            ? $this->moduleFromInput($validated['entity_type'])
            : null;
    }

    private function applyModuleFilter($query, string $module): void
    {
        $entityType = $this->resolveEntityType($module);

        $query
            ->where('entity_type', $entityType)
            ->where(function ($nested) use ($module, $entityType) {
                $nested->where('options->_module', $module)
                    ->orWhere(function ($legacy) use ($module, $entityType) {
                        $legacy->whereNull('options->_module')
                            ->where('entity_type', $entityType)
                            ->whereRaw('? = ?', [$this->fallbackModuleForEntityType($entityType), $module]);
                    });
            });
    }

    private function normalizeModule(string $module): string
    {
        return match ($module) {
            'account', 'accounts', 'company' => 'companies',
            'contact' => 'contacts',
            'opportunity' => 'opportunities',
            'product' => 'products',
            default => $module,
        };
    }

    private function moduleFromInput(string $value): ?string
    {
        $module = $this->normalizeModule($value);

        return array_key_exists($module, self::SUPPORTED_MODULES) ? $module : null;
    }

    private function fallbackModuleForEntityType(string $entityType): ?string
    {
        return match ($entityType) {
            Contact::class => 'contacts',
            Account::class => 'companies',
            CrmEntity::class => 'opportunities',
            Opportunity::class => 'opportunities',
            Product::class => 'products',
            default => null,
        };
    }

    private function moduleTotals(int $tenantId): array
    {
        $totals = array_fill_keys(array_keys(self::SUPPORTED_MODULES), 0);

        CustomField::query()
            ->where('tenant_id', $tenantId)
            ->get(['entity_type', 'options'])
            ->each(function (CustomField $field) use (&$totals) {
                $module = $field->options['_module'] ?? $this->fallbackModuleForEntityType($field->entity_type);

                if ($module && array_key_exists($module, $totals)) {
                    $totals[$module]++;
                }
            });

        return $totals;
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
