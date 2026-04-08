<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SearchService
{
    public function search(array $filters): array
    {
        $validated = $this->validateFilters($filters);
        $entityTypes = $this->normalizeEntityTypes($validated['entity_types'] ?? null);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $accounts = in_array('accounts', $entityTypes, true)
            ? $this->buildAccountsQuery($validated)->paginate($perPage, ['*'], 'accounts_page', $page)
            : null;
        $contacts = in_array('contacts', $entityTypes, true)
            ? $this->buildContactsQuery($validated)->paginate($perPage, ['*'], 'contacts_page', $page)
            : null;
        $crmEntities = in_array('crm-entities', $entityTypes, true)
            ? $this->buildCrmEntitiesQuery($validated)->paginate($perPage, ['*'], 'crm_entities_page', $page)
            : null;

        return [
            'filters' => [
                'entity_types' => $entityTypes,
                'query' => $validated['query'] ?? null,
                'tag_uids' => $validated['tag_uids'] ?? [],
                'created_from' => $validated['created_from'] ?? null,
                'created_to' => $validated['created_to'] ?? null,
                'owner_user_uid' => $validated['owner_user_uid'] ?? null,
                'custom_field_filters' => $validated['custom_field_filters'] ?? [],
                'sort_by' => $validated['sort_by'] ?? 'created_at',
                'sort_direction' => $validated['sort_direction'] ?? 'desc',
                'page' => $page,
                'per_page' => $perPage,
            ],
            'results' => [
                'accounts' => $accounts?->items() ?? [],
                'contacts' => $contacts?->items() ?? [],
                'crm_entities' => $crmEntities?->items() ?? [],
            ],
            'totals' => [
                'accounts' => $accounts?->total() ?? 0,
                'contacts' => $contacts?->total() ?? 0,
                'crm_entities' => $crmEntities?->total() ?? 0,
            ],
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'sort_by' => $validated['sort_by'] ?? 'created_at',
                'sort_direction' => $validated['sort_direction'] ?? 'desc',
                'accounts' => $this->paginationMeta($accounts),
                'contacts' => $this->paginationMeta($contacts),
                'crm_entities' => $this->paginationMeta($crmEntities),
            ],
            'total' => ($accounts?->total() ?? 0) + ($contacts?->total() ?? 0) + ($crmEntities?->total() ?? 0),
        ];
    }

    public function export(array $filters): array
    {
        $validated = $this->validateFilters($filters, true);
        $entityTypes = $this->normalizeEntityTypes($validated['entity_types'] ?? null);

        $accounts = in_array('accounts', $entityTypes, true)
            ? $this->buildAccountsQuery($validated)->get()
            : collect();
        $contacts = in_array('contacts', $entityTypes, true)
            ? $this->buildContactsQuery($validated)->get()
            : collect();
        $crmEntities = in_array('crm-entities', $entityTypes, true)
            ? $this->buildCrmEntitiesQuery($validated)->get()
            : collect();

        return [
            'filters' => [
                'entity_types' => $entityTypes,
                'query' => $validated['query'] ?? null,
                'tag_uids' => $validated['tag_uids'] ?? [],
                'created_from' => $validated['created_from'] ?? null,
                'created_to' => $validated['created_to'] ?? null,
                'owner_user_uid' => $validated['owner_user_uid'] ?? null,
                'custom_field_filters' => $validated['custom_field_filters'] ?? [],
                'sort_by' => $validated['sort_by'] ?? 'created_at',
                'sort_direction' => $validated['sort_direction'] ?? 'desc',
                'format' => $validated['format'] ?? 'json',
            ],
            'results' => [
                'accounts' => $accounts->all(),
                'contacts' => $contacts->all(),
                'crm_entities' => $crmEntities->all(),
            ],
            'totals' => [
                'accounts' => $accounts->count(),
                'contacts' => $contacts->count(),
                'crm_entities' => $crmEntities->count(),
            ],
            'total' => $accounts->count() + $contacts->count() + $crmEntities->count(),
        ];
    }

    public function exportAsCsv(array $filters): string
    {
        $export = $this->export(array_merge($filters, ['format' => 'csv']));
        $rows = [];

        foreach ($export['results']['accounts'] as $account) {
            $rows[] = $this->flattenForCsv('account', $account);
        }

        foreach ($export['results']['contacts'] as $contact) {
            $rows[] = $this->flattenForCsv('contact', $contact);
        }

        foreach ($export['results']['crm_entities'] as $crmEntity) {
            $rows[] = $this->flattenForCsv('crm-entity', $crmEntity);
        }

        $headers = [
            'entity_type',
            'uid',
            'display_name',
            'owner_user_uid',
            'tags',
            'custom_fields',
            'payload',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['entity_type'],
                $row['uid'],
                $row['display_name'],
                $row['owner_user_uid'],
                $row['tags'],
                $row['custom_fields'],
                $row['payload'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }

    private function buildAccountsQuery(array $filters): Builder
    {
        return $this->applySorting(
            $this->applyCommonFilters(Account::query()->with(['tags', 'customFieldValues.customField']), $filters),
            Account::class,
            $filters
        )->when(!empty($filters['query']), function (Builder $query) use ($filters) {
            $term = '%' . $filters['query'] . '%';
            $query->where(function (Builder $nested) use ($term) {
                $nested->where('name', 'like', $term)
                    ->orWhere('document', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('industry', 'like', $term)
                    ->orWhere('website', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        });
    }

    private function buildContactsQuery(array $filters): Builder
    {
        return $this->applySorting(
            $this->applyCommonFilters(Contact::query()->with(['account', 'tags', 'customFieldValues.customField']), $filters),
            Contact::class,
            $filters
        )->when(!empty($filters['query']), function (Builder $query) use ($filters) {
            $term = '%' . $filters['query'] . '%';
            $query->where(function (Builder $nested) use ($term) {
                $nested->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('position', 'like', $term);
            });
        });
    }

    private function buildCrmEntitiesQuery(array $filters): Builder
    {
        return $this->applySorting(
            $this->applyCommonFilters(CrmEntity::query()->with(['tags', 'customFieldValues.customField']), $filters),
            CrmEntity::class,
            $filters
        )->when(!empty($filters['query']), function (Builder $query) use ($filters) {
            $term = '%' . $filters['query'] . '%';
            $query->where(function (Builder $nested) use ($term) {
                $nested->where('type', 'like', $term)
                    ->orWhereRaw('CAST(profile_data AS TEXT) LIKE ?', [$term]);
            });
        });
    }

    private function applyCommonFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['tag_uids']), function (Builder $builder) use ($filters) {
                $builder->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('uid', $filters['tag_uids']));
            })
            ->when(!empty($filters['created_from']), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when(!empty($filters['owner_user_uid']), function (Builder $builder) use ($filters) {
                $ownerId = User::query()->where('uid', $filters['owner_user_uid'])->value('id');
                $builder->where('owner_user_id', $ownerId ?: 0);
            })
            ->when(!empty($filters['custom_field_filters']), function (Builder $builder) use ($filters) {
                foreach ($filters['custom_field_filters'] as $fieldFilter) {
                    $builder->whereHas('customFieldValues', function (Builder $customValueQuery) use ($fieldFilter) {
                        $customValueQuery
                            ->whereHas('customField', fn (Builder $customFieldQuery) => $customFieldQuery->where('uid', $fieldFilter['custom_field_uid']))
                            ->where('value', 'like', '%' . $fieldFilter['value'] . '%');
                    });
                }
            });
    }

    private function applySorting(Builder $query, string $modelClass, array $filters): Builder
    {
        $direction = $filters['sort_direction'] ?? 'desc';
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $allowed = $this->allowedSortsFor($modelClass);
        $column = $allowed[$sortBy] ?? $allowed['created_at'];

        return $query->orderBy($column, $direction);
    }

    private function allowedSortsFor(string $modelClass): array
    {
        return match ($modelClass) {
            Account::class => [
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'name' => 'name',
                'email' => 'email',
            ],
            Contact::class => [
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'name' => 'first_name',
                'email' => 'email',
            ],
            CrmEntity::class => [
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'type' => 'type',
            ],
            default => [
                'created_at' => 'created_at',
            ],
        };
    }

    private function validateFilters(array $filters, bool $forExport = false): array
    {
        $validator = Validator::make($filters, [
            'entity_types' => 'nullable|array',
            'entity_types.*' => 'string|in:accounts,contacts,crm-entities',
            'query' => 'nullable|string|max:255',
            'tag_uids' => 'nullable|array',
            'tag_uids.*' => 'uuid',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'owner_user_uid' => 'nullable|uuid',
            'custom_field_filters' => 'nullable|array',
            'custom_field_filters.*.custom_field_uid' => 'required_with:custom_field_filters|uuid',
            'custom_field_filters.*.value' => 'nullable',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:created_at,updated_at,name,email,type',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'format' => 'nullable|string|in:json,csv',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['created_from']) && !empty($validated['created_to']) && $validated['created_from'] > $validated['created_to']) {
            throw ValidationException::withMessages([
                'created_from' => ['La fecha inicial no puede ser mayor que la fecha final'],
            ]);
        }

        return $validated;
    }

    private function normalizeEntityTypes(?array $entityTypes): array
    {
        return $entityTypes ?: ['accounts', 'contacts', 'crm-entities'];
    }

    private function paginationMeta($paginator): ?array
    {
        if (!$paginator) {
            return null;
        }

        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    private function flattenForCsv(string $entityType, Model $entity): array
    {
        $displayName = match ($entityType) {
            'account' => $entity->name ?? $entity->uid,
            'contact' => trim(($entity->first_name ?? '') . ' ' . ($entity->last_name ?? '')),
            'crm-entity' => $entity->display_name ?? $entity->uid,
            default => $entity->uid,
        };

        $tags = method_exists($entity, 'tags')
            ? $entity->tags->pluck('name')->implode('|')
            : '';

        $customFields = method_exists($entity, 'customFieldValues')
            ? $entity->customFieldValues
                ->map(fn ($value) => ($value->customField?->key ?? 'field') . ':' . json_encode($value->value))
                ->implode('|')
            : '';

        return [
            'entity_type' => $entityType,
            'uid' => $entity->uid,
            'display_name' => $displayName,
            'owner_user_uid' => $entity->owner_user_uid ?? null,
            'tags' => $tags,
            'custom_fields' => $customFields,
            'payload' => json_encode($entity->toArray()),
        ];
    }
}
