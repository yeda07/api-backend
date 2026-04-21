<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Document;
use App\Models\DocumentType;
use App\Repositories\AlertRuleRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentTypeRepository;
use App\Repositories\DocumentVersionRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentTypeRepository $documentTypeRepository,
        private readonly DocumentVersionRepository $documentVersionRepository,
        private readonly AlertRuleRepository $alertRuleRepository
    ) {
    }

    public function getByEntity(string $entityType, string $entityUid)
    {
        $entity = $this->resolveEntity($entityType, $entityUid);

        return $this->documentRepository->forEntity($entity);
    }

    public function getByAccount(string $accountUid)
    {
        $account = $this->resolveAccount($accountUid);

        return $this->documentRepository->forAccount($account->getKey());
    }

    public function getByUid(string $uid): Document
    {
        return $this->documentRepository->findByUid($uid);
    }

    public function upload(array $data, UploadedFile $file): Document
    {
        $validated = $this->validatePayload($data);
        $entity = $this->resolveEntity($validated['entity_type'], $validated['entity_uid']);
        $account = $this->resolveAccountForDocument($entity, $validated['account_uid'] ?? null);
        $documentType = $this->resolveDocumentType($validated['document_type_uid'] ?? null);

        $this->validateFile($file);
        $this->ensureTypedDocumentHasAccount($documentType, $account);

        if ($account && $documentType) {
            $existing = $this->documentRepository->findByAccountAndType($account->getKey(), $documentType->getKey());

            if ($existing) {
                return $this->replaceDocument($existing->uid, $validated, $file, $entity);
            }
        }

        return DB::transaction(function () use ($validated, $file, $entity, $account, $documentType) {
            $stored = $this->storeFile($file);
            $expirationDate = $this->resolveExpirationDate(
                $validated['issue_date'] ?? null,
                $validated['expiration_date'] ?? null,
                $documentType
            );

            $document = $this->documentRepository->create([
                'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
                'uploaded_by_user_id' => auth()->id(),
                'account_id' => $account?->getKey(),
                'document_type_id' => $documentType?->getKey(),
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => $file->getSize(),
                'issue_date' => $validated['issue_date'] ?? null,
                'expiration_date' => $expirationDate,
                'status' => $this->calculateStatus($expirationDate, $documentType?->getKey()),
                'is_active' => true,
                'version_number' => 1,
                'documentable_type' => get_class($entity),
                'documentable_id' => $entity->getKey(),
            ]);

            $this->documentVersionRepository->createFromDocument($document);

            return $document;
        });
    }

    public function updateDocument(string $uid, array $data, ?UploadedFile $file = null): Document
    {
        $document = $this->documentRepository->findByUid($uid);
        $validated = $this->validatePayload($data, true);

        if ($file) {
            return $this->replaceDocument($uid, $validated, $file);
        }

        return DB::transaction(function () use ($document, $validated) {
            $entity = (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated))
                ? $this->resolveEntity(
                    $validated['entity_type'] ?? $this->normalizeEntityType($document->documentable_type),
                    $validated['entity_uid'] ?? $document->documentable?->uid
                )
                : $document->documentable;

            $documentType = array_key_exists('document_type_uid', $validated)
                ? $this->resolveDocumentType($validated['document_type_uid'] ?? null)
                : $document->documentType;

            $account = array_key_exists('account_uid', $validated)
                ? $this->resolveAccount($validated['account_uid'])
                : ($document->account ?? ($entity instanceof Account ? $entity : null));

            $this->ensureTypedDocumentHasAccount($documentType, $account);
            $this->ensureUniqueAccountType($document, $account, $documentType);

            $issueDate = $validated['issue_date'] ?? $document->issue_date?->toDateString();
            $expirationDate = $this->resolveExpirationDate(
                $issueDate,
                $validated['expiration_date'] ?? $document->expiration_date?->toDateString(),
                $documentType
            );

            $updated = $this->documentRepository->update($document, [
                'owner_user_id' => $entity?->owner_user_id ?? $document->owner_user_id ?? auth()->id(),
                'account_id' => $account?->getKey(),
                'document_type_id' => $documentType?->getKey(),
                'issue_date' => $issueDate,
                'expiration_date' => $expirationDate,
                'status' => $this->calculateStatus($expirationDate, $documentType?->getKey()),
                'documentable_type' => $entity ? get_class($entity) : $document->documentable_type,
                'documentable_id' => $entity?->getKey() ?? $document->documentable_id,
            ]);

            $this->documentVersionRepository->syncCurrent($updated);

            return $updated;
        });
    }

    public function replaceDocument(string $uid, array $data, UploadedFile $file, ?object $resolvedEntity = null): Document
    {
        $document = $this->documentRepository->findByUid($uid);
        $validated = $this->validatePayload($data, true);
        $this->validateFile($file);

        return DB::transaction(function () use ($document, $validated, $file, $resolvedEntity) {
            $entity = $resolvedEntity
                ?? ((array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated))
                    ? $this->resolveEntity($validated['entity_type'] ?? $this->normalizeEntityType($document->documentable_type), $validated['entity_uid'] ?? $document->documentable?->uid)
                    : $document->documentable);

            $documentType = array_key_exists('document_type_uid', $validated)
                ? $this->resolveDocumentType($validated['document_type_uid'] ?? null)
                : $document->documentType;

            $account = array_key_exists('account_uid', $validated)
                ? $this->resolveAccount($validated['account_uid'])
                : ($document->account ?? ($entity instanceof Account ? $entity : null));

            $this->ensureTypedDocumentHasAccount($documentType, $account);
            $this->ensureUniqueAccountType($document, $account, $documentType);

            $this->documentVersionRepository->markCurrentAsReplaced($document);

            $issueDate = $validated['issue_date'] ?? $document->issue_date?->toDateString();
            $expirationDate = $this->resolveExpirationDate(
                $issueDate,
                $validated['expiration_date'] ?? $document->expiration_date?->toDateString(),
                $documentType
            );

            $stored = $this->storeFile($file);
            $updated = $this->documentRepository->update($document, [
                'owner_user_id' => $entity?->owner_user_id ?? $document->owner_user_id ?? auth()->id(),
                'uploaded_by_user_id' => auth()->id(),
                'account_id' => $account?->getKey(),
                'document_type_id' => $documentType?->getKey(),
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => $file->getSize(),
                'issue_date' => $issueDate,
                'expiration_date' => $expirationDate,
                'status' => $this->calculateStatus($expirationDate, $documentType?->getKey()),
                'is_active' => true,
                'version_number' => $document->version_number + 1,
                'replaced_at' => null,
                'documentable_type' => $entity ? get_class($entity) : $document->documentable_type,
                'documentable_id' => $entity?->getKey() ?? $document->documentable_id,
            ]);

            $this->documentVersionRepository->createFromDocument($updated);

            return $updated;
        });
    }

    public function download(string $uid): array
    {
        $document = $this->documentRepository->findByUid($uid);

        if (!Storage::disk($document->disk)->exists($document->path)) {
            throw ValidationException::withMessages([
                'document' => ['El archivo no existe en el almacenamiento'],
            ]);
        }

        return [
            'document' => $document,
            'content' => Storage::disk($document->disk)->get($document->path),
        ];
    }

    public function calculateStatus(null|string|Carbon $expirationDate, ?int $documentTypeId = null): string
    {
        if (!$expirationDate) {
            return 'valid';
        }

        $expiration = Carbon::parse($expirationDate)->startOfDay();
        $today = now()->startOfDay();

        if ($today->gt($expiration)) {
            return 'expired';
        }

        $daysBefore = $this->alertRuleRepository->maxDaysBeforeForType($documentTypeId);

        if ($daysBefore > 0 && $today->diffInDays($expiration, false) <= $daysBefore) {
            return 'expiring';
        }

        return 'valid';
    }

    public function refreshStatuses(): void
    {
        $this->documentRepository->expirable()->each(function (Document $document) {
            $status = $this->calculateStatus($document->expiration_date, $document->document_type_id);

            if ($document->status !== $status) {
                $this->documentRepository->update($document, ['status' => $status]);
                $this->documentVersionRepository->syncCurrent($document->fresh());
            }
        });
    }

    private function validatePayload(array $data, bool $partial = false): array
    {
        $rules = [
            'entity_type' => [$partial ? 'sometimes' : 'required', 'string'],
            'entity_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'account_uid' => ['sometimes', 'uuid'],
            'document_type_uid' => ['nullable', 'uuid'],
            'issue_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    private function validateFile(UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if ($mime !== 'application/pdf') {
            throw ValidationException::withMessages([
                'file' => ['Solo se permiten archivos PDF'],
            ]);
        }
    }

    private function storeFile(UploadedFile $file): array
    {
        $disk = config('filesystems.documents_disk', config('filesystems.default', 'local'));
        $path = $file->store('documents/' . auth()->user()->tenant_id, $disk);

        if (!$path) {
            throw ValidationException::withMessages([
                'file' => ['No fue posible almacenar el archivo'],
            ]);
        }

        return [
            'disk' => $disk,
            'path' => $path,
        ];
    }

    private function resolveEntity(string $entityType, string $entityUid)
    {
        $entity = find_entity_by_uid($entityType, $entityUid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad no existe o no es visible para este usuario'],
            ]);
        }

        return $entity;
    }

    private function resolveAccount(string $accountUid): Account
    {
        return Account::query()->where('uid', $accountUid)->firstOrFail();
    }

    private function resolveDocumentType(?string $documentTypeUid): ?DocumentType
    {
        if (!$documentTypeUid) {
            return null;
        }

        return $this->documentTypeRepository->findByUid($documentTypeUid);
    }

    private function resolveAccountForDocument(object $entity, ?string $accountUid): ?Account
    {
        if ($accountUid) {
            return $this->resolveAccount($accountUid);
        }

        if ($entity instanceof Account) {
            return $entity;
        }

        return null;
    }

    private function ensureTypedDocumentHasAccount(?DocumentType $documentType, ?Account $account): void
    {
        if ($documentType && !$account) {
            throw ValidationException::withMessages([
                'account_uid' => ['Los documentos tipificados B2G deben asociarse a una cuenta'],
            ]);
        }
    }

    private function ensureUniqueAccountType(Document $currentDocument, ?Account $account, ?DocumentType $documentType): void
    {
        if (!$account || !$documentType) {
            return;
        }

        $existing = $this->documentRepository->findByAccountAndType($account->getKey(), $documentType->getKey());

        if ($existing && $existing->uid !== $currentDocument->uid) {
            throw ValidationException::withMessages([
                'document_type_uid' => ['Ya existe un documento activo de este tipo para la cuenta'],
            ]);
        }
    }

    private function resolveExpirationDate(?string $issueDate, ?string $expirationDate, ?DocumentType $documentType): ?string
    {
        if ($expirationDate) {
            return $expirationDate;
        }

        if ($issueDate && $documentType?->validity_days) {
            return Carbon::parse($issueDate)->addDays($documentType->validity_days)->toDateString();
        }

        return null;
    }

    private function normalizeEntityType(?string $className): string
    {
        return match ($className) {
            Account::class => 'account',
            Contact::class => 'contact',
            CrmEntity::class => 'crm-entity',
            default => 'account',
        };
    }
}
