<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Repositories\ContactRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactService
{
    protected $repo;

    public function __construct(ContactRepository $repo, private readonly ExportService $exportService)
    {
        $this->repo = $repo;
    }

    public function getAll(array $filters = [])
    {
        return $this->repo->all($filters);
    }

    public function getByUid(string $uid)
    {
        return $this->repo->findByUid($uid);
    }

    public function create(array $data)
    {
        PlanLimitService::check('contacts');

        $data = $this->normalizeFrontendPayload($data);
        $this->validate($data);
        $data = $this->normalizeAccountReference($data);

        try {
            return $this->repo->create($data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function update(string $uid, array $data)
    {
        $data = $this->normalizeFrontendPayload($data);
        $this->validate($data);
        $data = $this->normalizeAccountReference($data);

        try {
            return $this->repo->update($uid, $data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function delete(string $uid)
    {
        return $this->repo->delete($uid);
    }

    public function checkDuplicate(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'nullable|email|max:150',
            'tax_id' => 'nullable|string|max:50',
            'document' => 'nullable|string|max:50',
            'exclude_uid' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $taxId = $validated['tax_id'] ?? $validated['document'] ?? null;

        return [
            'email_duplicate' => $this->repo->emailExists($validated['email'] ?? null, $validated['exclude_uid'] ?? null),
            'tax_id_duplicate' => $taxId
                ? Account::query()->where('document', $taxId)->exists()
                : false,
        ];
    }

    public function export(array $payload)
    {
        $validated = Validator::make($payload, [
            'format' => 'required|string|in:excel,pdf,csv',
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'filters' => 'nullable|array',
        ])->validate();

        $filters = $validated['filters'] ?? [];
        $rows = Contact::query()
            ->with('account')
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50000)
            ->get()
            ->map(fn (Contact $contact) => [
                'uid' => $contact->uid,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'position' => $contact->position,
                'account' => $contact->account?->name,
                'created_at' => $contact->created_at?->toDateTimeString(),
            ]);

        return $this->exportService->file('contactos', $rows, [
            'format' => $validated['format'],
            'fields' => $validated['fields'] ?? [],
            'filters' => $filters,
        ]);
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|max:150',
            'last_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'account_uid' => 'nullable|exists:accounts,uid',
            'account_id' => 'prohibited',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function normalizeAccountReference(array $data): array
    {
        unset($data['account_id']);

        if (array_key_exists('company_uid', $data) && !array_key_exists('account_uid', $data)) {
            $data['account_uid'] = $data['company_uid'];
        }

        if (array_key_exists('account_uid', $data)) {
            $data['account_id'] = Account::where('uid', $data['account_uid'])->value('id');
            unset($data['account_uid']);
        }

        unset($data['company_uid']);

        return $data;
    }

    private function normalizeFrontendPayload(array $data): array
    {
        if (!array_key_exists('first_name', $data) && !empty($data['name'])) {
            $parts = preg_split('/\s+/', trim($data['name']), 2);
            $data['first_name'] = $parts[0] ?? $data['name'];
            $data['last_name'] = $parts[1] ?? null;
        }

        if (array_key_exists('job_title', $data) && !array_key_exists('position', $data)) {
            $data['position'] = $data['job_title'];
        }

        unset($data['name'], $data['job_title'], $data['type'], $data['status'], $data['id_number'], $data['institution_type'], $data['is_public_entity']);

        return $data;
    }
}
