<?php

namespace App\Services;

use App\Models\Account;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentTypeRepository;
use Illuminate\Validation\ValidationException;

class DocumentValidationService
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypeRepository,
        private readonly DocumentRepository $documentRepository
    ) {
    }

    public function validateRequiredDocuments(string $accountUid): array
    {
        $missing = $this->getMissingDocuments($accountUid);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'documents' => ['Faltan documentos requeridos para continuar'],
                'missing_documents' => array_map(fn (array $item) => $item['message'], $missing),
            ]);
        }

        return [
            'valid' => true,
            'missing_documents' => [],
        ];
    }

    public function ensureReadyForAccount(Account $account): void
    {
        $this->validateRequiredDocuments($account->uid);
    }

    public function getMissingDocuments(string $accountUid): array
    {
        $account = Account::query()->where('uid', $accountUid)->firstOrFail();
        $missing = [];

        foreach ($this->documentTypeRepository->activeRequired() as $documentType) {
            $document = $this->documentRepository->findByAccountAndType($account->getKey(), $documentType->getKey());

            if (!$document) {
                $missing[] = [
                    'document_type_uid' => $documentType->uid,
                    'document_type_name' => $documentType->name,
                    'status' => 'missing',
                    'message' => "Falta cargar {$documentType->name}",
                ];
                continue;
            }

            if ($document->status === 'expired') {
                $missing[] = [
                    'document_type_uid' => $documentType->uid,
                    'document_type_name' => $documentType->name,
                    'document_uid' => $document->uid,
                    'status' => 'expired',
                    'message' => "{$documentType->name} está vencido",
                ];
            }
        }

        return $missing;
    }
}
