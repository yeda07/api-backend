<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\User;
use App\Support\ApiIndex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class OpportunityService
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ExportService $exportService,
        private readonly CompetitiveIntelligenceService $competitiveIntelligenceService,
        private readonly ActivityService $activityService
    ) {}

    public function stages()
    {
        return OpportunityStage::query()->orderBy('position')->get();
    }

    public function createStage(array $data): OpportunityStage
    {
        $validated = $this->validateStage($data);

        return OpportunityStage::query()->create($validated);
    }

    public function updateStage(string $uid, array $data): OpportunityStage
    {
        $stage = OpportunityStage::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateStage($data, true);
        $stage->update($validated);

        return $stage->fresh();
    }

    public function deleteStage(string $uid): void
    {
        $stage = OpportunityStage::query()->where('uid', $uid)->firstOrFail();

        if ($stage->opportunities()->exists()) {
            throw ValidationException::withMessages([
                'stage' => ['No puedes eliminar una etapa con oportunidades asociadas'],
            ]);
        }

        $stage->delete();
    }

    public function opportunities(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'stage_uid' => 'nullable|uuid',
            'owner_user_uid' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
        ])->validate();

        $query = Opportunity::query()
            ->with(['stage', 'owner'])
            ->when(! empty($validated['stage_uid']), function ($query) use ($validated) {
                $stageId = OpportunityStage::query()->where('uid', $validated['stage_uid'])->value('id');
                $query->where('stage_id', $stageId ?: 0);
            })
            ->when(! empty($validated['owner_user_uid']), function ($query) use ($validated) {
                $ownerId = User::query()->where('uid', $validated['owner_user_uid'])->value('id');
                $query->where('owner_user_id', $ownerId ?: 0);
            })
            ->when(! empty($validated['search']), fn ($query) => $this->applyOpportunitySearch($query, $validated['search']))
            ->orderByDesc('created_at');

        $result = ApiIndex::paginateOrGet($query, $filters, 'opportunities_page');

        return $this->mapOpportunityIndexResult($result);
    }

    public function getOpportunity(string $uid): Opportunity
    {
        return Opportunity::query()
            ->with([
                'stage',
                'owner',
                'opportunityable',
                'project',
                'lostReasons.competitor',
                'activities.owner',
                'activities.assignedUser',
                'quotations.items.product',
                'quotations.items.catalogProduct',
                'quotations.items.warehouse',
            ])
            ->where('uid', $uid)
            ->firstOrFail();
    }

    public function createOpportunity(array $data): Opportunity
    {
        $validated = $this->validateOpportunity($data);

        return DB::transaction(function () use ($validated) {
            $stage = $this->resolveStage($validated['stage_uid']);
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);

            $opportunity = Opportunity::query()->create([
                'owner_user_id' => $this->resolveOwnerUserId($validated['owner_user_uid'] ?? null, $entity?->owner_user_id),
                'stage_id' => $stage->getKey(),
                'opportunityable_type' => $entity ? get_class($entity) : null,
                'opportunityable_id' => $entity?->getKey(),
                'title' => $validated['title'],
                'email' => $validated['email'] ?? null,
                'amount' => $validated['amount'] ?? 0,
                'currency' => $validated['currency'] ?? null,
                'expected_close_date' => $validated['expected_close_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'won_at' => $stage->is_won ? now() : null,
                'lost_at' => $stage->is_lost ? now() : null,
            ]);

            if ($stage->is_won) {
                $this->projectService->createFromOpportunityModel($opportunity->fresh(['opportunityable']), quietIfNoAccount: true);
            }

            return $opportunity->fresh(['stage', 'owner', 'opportunityable']);
        });
    }

    public function updateOpportunity(string $uid, array $data): Opportunity
    {
        $opportunity = Opportunity::query()->with(['stage', 'owner', 'opportunityable'])->where('uid', $uid)->firstOrFail();
        $validated = $this->validateOpportunity($data, true);

        return DB::transaction(function () use ($opportunity, $validated) {
            $payload = [];

            foreach (['title', 'email', 'amount', 'currency', 'expected_close_date', 'description'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('stage_uid', $validated)) {
                $stage = $this->resolveStage($validated['stage_uid']);
                $payload['stage_id'] = $stage->getKey();
                $payload['won_at'] = $stage->is_won ? now() : null;
                $payload['lost_at'] = $stage->is_lost ? now() : null;
            }

            if (array_key_exists('owner_user_uid', $validated)) {
                $payload['owner_user_id'] = $this->resolveOwnerUserId($validated['owner_user_uid']);
            }

            if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
                $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
                $payload['opportunityable_type'] = $entity ? get_class($entity) : null;
                $payload['opportunityable_id'] = $entity?->getKey();
            }

            $opportunity->update($payload);

            $opportunity = $opportunity->fresh(['stage', 'owner', 'opportunityable']);

            if ($opportunity->stage?->is_won) {
                $this->projectService->createFromOpportunityModel($opportunity, quietIfNoAccount: true);
            }

            return $opportunity;
        });
    }

    public function markWon(string $uid, array $data = []): array
    {
        $validated = Validator::make($data, [
            'notes' => 'nullable|string|max:2000',
            'comment' => 'nullable|string|max:2000',
        ])->validate();

        return DB::transaction(function () use ($uid, $validated) {
            $opportunity = $this->getOpportunity($uid);
            $opportunity->update([
                'won_at' => now(),
                'lost_at' => null,
            ]);

            $project = $this->projectService->createFromOpportunityModel($opportunity->fresh(['opportunityable']), quietIfNoAccount: true);
            $note = $validated['notes'] ?? $validated['comment'] ?? null;

            if ($note) {
                $this->activityService->create([
                    'type' => 'note',
                    'title' => 'Oportunidad marcada como ganada',
                    'description' => $note,
                    'status' => 'completed',
                    'scheduled_at' => now()->toDateTimeString(),
                    'entity_type' => 'opportunity',
                    'entity_uid' => $opportunity->uid,
                ]);
            }

            return [
                'opportunity' => $opportunity->fresh(['stage', 'owner', 'opportunityable', 'project']),
                'project' => $project,
            ];
        });
    }

    public function markLost(string $uid, array $data = []): array
    {
        $validated = Validator::make($data, [
            'lost_reasons' => 'nullable|array',
            'lost_reasons.*.category' => 'nullable|string|max:255',
            'lost_reasons.*.reason_type' => 'nullable|string',
            'lost_reasons.*.competitor_uid' => 'nullable|uuid',
            'lost_reasons.*.detail' => 'nullable|string',
            'lost_reasons.*.details' => 'nullable|string',
            'lost_reasons.*.summary' => 'nullable|string|max:255',
            'reasons' => 'nullable|array',
            'reasons.*.category' => 'nullable|string|max:255',
            'reasons.*.reason_type' => 'nullable|string',
            'reasons.*.competitor_uid' => 'nullable|uuid',
            'reasons.*.detail' => 'nullable|string',
            'reasons.*.details' => 'nullable|string',
            'reasons.*.summary' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'comment' => 'nullable|string|max:2000',
        ])->validate();

        return DB::transaction(function () use ($uid, $validated) {
            $opportunity = $this->getOpportunity($uid);
            $opportunity->update([
                'won_at' => null,
                'lost_at' => now(),
            ]);

            $reasons = $validated['lost_reasons'] ?? $validated['reasons'] ?? [];
            $createdReasons = collect($reasons)
                ->map(fn (array $reason) => $this->competitiveIntelligenceService->createLostReason(
                    $this->lostReasonPayload($opportunity, $reason)
                ))
                ->values();

            $note = $validated['notes'] ?? $validated['comment'] ?? null;

            if ($note) {
                $this->activityService->create([
                    'type' => 'note',
                    'title' => 'Oportunidad marcada como perdida',
                    'description' => $note,
                    'status' => 'completed',
                    'scheduled_at' => now()->toDateTimeString(),
                    'entity_type' => 'opportunity',
                    'entity_uid' => $opportunity->uid,
                ]);
            }

            return [
                'opportunity' => $opportunity->fresh(['stage', 'owner', 'opportunityable', 'lostReasons.competitor']),
                'lost_reasons' => $createdReasons,
            ];
        });
    }

    private function lostReasonPayload(Opportunity $opportunity, array $reason): array
    {
        $details = $reason['detail'] ?? $reason['details'] ?? null;
        $category = $reason['category'] ?? null;

        $payload = [
            'opportunity_uid' => $opportunity->uid,
            'lost_reason_category' => $category,
            'competitor_uid' => $reason['competitor_uid'] ?? null,
            'lost_reason_detail' => $details,
            'summary' => $reason['summary'] ?? $details ?? $category ?? 'Oportunidad perdida',
            'lost_at' => now()->toDateString(),
            'estimated_value' => $opportunity->amount,
            'currency' => $opportunity->currency,
        ];

        if (! empty($reason['reason_type']) || ! $category) {
            $payload['reason_type'] = $reason['reason_type'] ?? 'other';
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    public function deleteOpportunity(string $uid): void
    {
        Opportunity::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function import(array $data): array
    {
        $validated = Validator::make($data, [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'stage_uid' => 'nullable|uuid',
        ])->validate();

        $rows = $this->readImportRows($validated['file']);
        $created = [];
        $errors = [];
        $defaultStageUid = $validated['stage_uid'] ?? $this->defaultStageUid();

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $payload = $this->importRowPayload($row, $defaultStageUid);

                if (empty($payload['title'])) {
                    continue;
                }

                $created[] = $this->createOpportunity($payload);
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $line,
                    'message' => $e instanceof ValidationException
                        ? collect($e->errors())->flatten()->implode(' ')
                        : $e->getMessage(),
                ];
            }
        }

        return [
            'created_count' => count($created),
            'failed_count' => count($errors),
            'opportunities' => $created,
            'errors' => $errors,
        ];
    }

    public function template()
    {
        return $this->exportService->file('opportunities-import-template', [[
            'titulo' => 'TechNova S.A.',
            'monto' => 15000000,
            'moneda' => 'COP',
            'fecha_cierre_esperada' => '2026-06-30',
            'email' => 'contacto@technova.com',
            'descripcion' => 'Cliente interesado en implementacion CRM',
        ]], [
            'format' => 'excel',
        ]);
    }

    public function board(array $filters = []): array
    {
        $validated = Validator::make($filters, [
            'search' => 'nullable|string|max:255',
            'origin' => 'nullable|string|max:255',
            'product' => 'nullable|string|max:255',
        ])->validate();

        $stages = OpportunityStage::query()->orderBy('position')->get();
        $opportunityQuery = Opportunity::query()->with(['stage', 'owner']);

        if (! empty($validated['search'])) {
            $this->applyOpportunitySearch($opportunityQuery, $validated['search']);
        }

        if (! empty($validated['origin'])) {
            $this->applyOpportunityOriginFilter($opportunityQuery, $validated['origin']);
        }

        if (! empty($validated['product'])) {
            $this->applyOpportunityProductFilter($opportunityQuery, $validated['product']);
        }

        $result = ApiIndex::paginateOrGet($opportunityQuery->latest(), $filters, 'opportunities_board_page');
        $items = collect(method_exists($result, 'items') ? $result->items() : $result)
            ->map(fn (Opportunity $opportunity) => $this->serializeOpportunityIndex($opportunity));
        $opportunities = $items->groupBy('stage_id');

        $payload = [
            'stages' => $stages->map(function (OpportunityStage $stage) use ($opportunities) {
                $items = $opportunities->get($stage->getKey(), collect())->values();

                return [
                    'stage' => $stage,
                    'summary' => [
                        'count' => $items->count(),
                        'amount' => round((float) $items->sum(fn (array $opportunity) => (float) $opportunity['amount']), 2),
                    ],
                    'items' => $items,
                ];
            })->values(),
        ];

        if (method_exists($result, 'currentPage')) {
            $payload['pagination'] = ApiIndex::meta($result)['pagination'];
        }

        return $payload;
    }

    private function mapOpportunityIndexResult($result)
    {
        if (method_exists($result, 'through')) {
            return $result->through(fn (Opportunity $opportunity) => $this->serializeOpportunityIndex($opportunity));
        }

        return collect($result)
            ->map(fn (Opportunity $opportunity) => $this->serializeOpportunityIndex($opportunity))
            ->values();
    }

    private function serializeOpportunityIndex(Opportunity $opportunity): array
    {
        return [
            'uid' => $opportunity->uid,
            'title' => $opportunity->title,
            'email' => $opportunity->email,
            'amount' => round((float) $opportunity->amount, 2),
            'currency' => $opportunity->currency,
            'expected_close_date' => $opportunity->expected_close_date,
            'description' => $opportunity->description,
            'stage_id' => $opportunity->stage_id,
            'stage_uid' => $opportunity->stage?->uid,
            'stage_name' => $opportunity->stage?->name,
            'stage' => $opportunity->stage ? [
                'uid' => $opportunity->stage->uid,
                'name' => $opportunity->stage->name,
                'key' => $opportunity->stage->key,
                'position' => $opportunity->stage->position,
                'probability' => $opportunity->stage->probability,
                'color' => $opportunity->stage->color,
                'is_won' => $opportunity->stage->is_won,
                'is_lost' => $opportunity->stage->is_lost,
            ] : null,
            'owner_user_uid' => $opportunity->owner?->uid,
            'owner' => $opportunity->owner ? [
                'uid' => $opportunity->owner->uid,
                'name' => $opportunity->owner->name,
                'email' => $opportunity->owner->email,
            ] : null,
            'opportunityable_type' => $opportunity->opportunityable_type,
            'opportunityable_uid' => $this->resolveMorphUid($opportunity->opportunityable_type, $opportunity->opportunityable_id),
            'won_at' => $opportunity->won_at,
            'lost_at' => $opportunity->lost_at,
            'created_at' => $opportunity->created_at,
            'updated_at' => $opportunity->updated_at,
        ];
    }

    private function resolveMorphUid(?string $class, ?int $id): ?string
    {
        if (! $class || ! $id || ! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
            return null;
        }

        return $class::withoutGlobalScopes()->whereKey($id)->value('uid');
    }

    private function applyOpportunitySearch($query, string $search): void
    {
        $query->where(function ($builder) use ($search) {
            $builder
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')
                ->orWhereHasMorph('opportunityable', [Account::class], function ($entityQuery) use ($search) {
                    $entityQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%');
                })
                ->orWhereHasMorph('opportunityable', [Contact::class], function ($entityQuery) use ($search) {
                    $entityQuery
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
                ->orWhereHasMorph('opportunityable', [CrmEntity::class], function ($entityQuery) use ($search) {
                    $entityQuery->where('type', 'like', '%'.$search.'%');
                });
        });
    }

    private function applyOpportunityOriginFilter($query, string $origin): void
    {
        $query->where(function ($builder) use ($origin) {
            $builder
                ->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($origin).'%'])
                ->orWhereHasMorph('opportunityable', [CrmEntity::class], function ($entityQuery) use ($origin) {
                    $entityQuery->where(function ($crmQuery) use ($origin) {
                        $crmQuery
                            ->where('profile_data->lead_origin', $origin)
                            ->orWhere('profile_data->origin', $origin);
                    });
                });
        });
    }

    private function applyOpportunityProductFilter($query, string $product): void
    {
        $search = '%'.mb_strtolower($product).'%';

        $query->where(function ($builder) use ($product, $search) {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$search])
                ->orWhereRaw('LOWER(description) LIKE ?', [$search])
                ->orWhereHasMorph('opportunityable', [CrmEntity::class], function ($entityQuery) use ($product) {
                    $entityQuery->where(function ($crmQuery) use ($product) {
                        $crmQuery
                            ->where('profile_data->product', $product)
                            ->orWhere('profile_data->product_uid', $product)
                            ->orWhere('profile_data->main_product', $product)
                            ->orWhere('profile_data->primary_product', $product);
                    });
                });
        });
    }

    public function summary(): array
    {
        $opportunities = Opportunity::query()->with('stage')->get();

        return [
            'totals' => [
                'count' => $opportunities->count(),
                'amount' => round((float) $opportunities->sum(fn ($opportunity) => (float) $opportunity->amount), 2),
                'won_count' => $opportunities->filter(fn ($opportunity) => $opportunity->stage?->is_won)->count(),
                'lost_count' => $opportunities->filter(fn ($opportunity) => $opportunity->stage?->is_lost)->count(),
            ],
            'by_stage' => OpportunityStage::query()
                ->orderBy('position')
                ->get()
                ->map(function (OpportunityStage $stage) {
                    $items = Opportunity::query()->where('stage_id', $stage->getKey())->get();

                    return [
                        'stage_uid' => $stage->uid,
                        'stage_name' => $stage->name,
                        'count' => $items->count(),
                        'amount' => round((float) $items->sum(fn ($opportunity) => (float) $opportunity->amount), 2),
                    ];
                })
                ->values(),
        ];
    }

    private function importRowPayload(array $row, string $defaultStageUid): array
    {
        $stageUid = $row['stage_uid'] ?? null;

        if (! $stageUid && ! empty($row['stage_key'])) {
            $stageUid = OpportunityStage::query()->where('key', $row['stage_key'])->value('uid');
        }

        $payload = [
            'stage_uid' => $stageUid ?: $defaultStageUid,
            'owner_user_uid' => $row['owner_user_uid'] ?? null,
            'title' => $row['title'] ?? $row['titulo'] ?? $row['lead_name'] ?? $row['nombre'] ?? $row['name'] ?? null,
            'amount' => $row['amount'] ?? $row['monto'] ?? $row['valor'] ?? 0,
            'currency' => $row['currency'] ?? $row['moneda'] ?? null,
            'expected_close_date' => $row['expected_close_date'] ?? $row['fecha_cierre_esperada'] ?? $row['fecha_cierre'] ?? null,
            'email' => $row['email'] ?? $row['correo'] ?? $row['lead_email'] ?? null,
            'description' => $row['description'] ?? $row['descripcion'] ?? $row['notas'] ?? $row['notes'] ?? null,
        ];

        if (! empty($row['account_uid'])) {
            $payload['entity_type'] = 'account';
            $payload['entity_uid'] = $row['account_uid'];
        } elseif (! empty($row['contact_uid'])) {
            $payload['entity_type'] = 'contact';
            $payload['entity_uid'] = $row['contact_uid'];
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    private function defaultStageUid(): string
    {
        return OpportunityStage::query()
            ->orderBy('position')
            ->value('uid')
            ?? throw ValidationException::withMessages([
                'stage_uid' => ['No hay etapas de oportunidad configuradas'],
            ]);
    }

    private function readImportRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension === 'xlsx'
            ? $this->readXlsxRows($file)
            : $this->readCsvRows($file);
    }

    private function readCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [];
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeImportHeaders($data);

                continue;
            }

            $rows[] = $this->combineImportRow($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(UploadedFile $file): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'file' => ['No fue posible leer el archivo XLSX'],
            ]);
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        $headers = null;
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->xlsxColumnIndex(preg_replace('/\d+/', '', $reference));
                $type = (string) $cell['t'];
                $value = (string) $cell->v;

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }

                $values[$columnIndex] = $value;
            }

            ksort($values);
            $values = array_values($values);

            if ($headers === null) {
                $headers = $this->normalizeImportHeaders($values);

                continue;
            }

            $rows[] = $this->combineImportRow($headers, $values);
        }

        return $rows;
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $strings = [];

        foreach ($shared->si as $item) {
            $strings[] = (string) ($item->t ?? collect($item->r)->map(fn ($run) => (string) $run->t)->implode(''));
        }

        return $strings;
    }

    private function normalizeImportHeaders(array $headers): array
    {
        return collect($headers)
            ->map(fn ($header) => str((string) $header)->lower()->snake()->toString())
            ->all();
    }

    private function combineImportRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? null;
        }

        return $row;
    }

    private function xlsxColumnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord(strtoupper($letter)) - 64);
        }

        return $index - 1;
    }

    private function validateStage(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'position' => 'sometimes|integer|min:1',
            'probability_percent' => 'sometimes|integer|min:0|max:100',
            'is_won' => 'sometimes|boolean',
            'is_lost' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateOpportunity(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'stage_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'owner_user_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => 'nullable|email|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'expected_close_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (! empty($validated['entity_type']) xor ! empty($validated['entity_uid'])) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid juntos'],
            ]);
        }

        return $validated;
    }

    private function resolveStage(string $uid): OpportunityStage
    {
        return OpportunityStage::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveEntity(?string $type, ?string $uid)
    {
        if (! $type && ! $uid) {
            return null;
        }

        $entity = find_entity_by_uid($type, $uid);

        if (! $entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad comercial no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveOwnerUserId(?string $uid, ?int $fallback = null): ?int
    {
        if (! $uid) {
            return $fallback ?? auth()->id();
        }

        $userId = User::query()->where('uid', $uid)->value('id');

        if (! $userId) {
            throw ValidationException::withMessages([
                'owner_user_uid' => ['El usuario no existe o no pertenece a este tenant'],
            ]);
        }

        return $userId;
    }
}
