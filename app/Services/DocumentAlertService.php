<?php

namespace App\Services;

use App\Models\DocumentAlert;
use App\Repositories\AlertRuleRepository;
use App\Repositories\DocumentAlertRepository;
use App\Repositories\DocumentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentAlertService
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly AlertRuleRepository $alertRuleRepository,
        private readonly DocumentAlertRepository $documentAlertRepository,
        private readonly DocumentService $documentService
    ) {
    }

    public function getPendingAlerts(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'status' => ['nullable', 'string', 'in:pending,sent,read'],
            'account_uid' => ['nullable', 'uuid'],
            'state' => ['nullable', 'string', 'in:valid,expiring,expired'],
        ])->validate();

        return $this->documentAlertRepository->pending($validated);
    }

    public function generateAlerts(): array
    {
        return DB::transaction(function () {
            $this->documentService->refreshStatuses();

            $created = [];

            foreach ($this->documentRepository->expirable() as $document) {
                $rules = $this->alertRuleRepository->activeForType($document->document_type_id);

                foreach ($rules as $rule) {
                    if (!$this->shouldTrigger($document->expiration_date, $rule->days_before)) {
                        continue;
                    }

                    $alertDate = now()->toDateString();
                    $existing = $this->documentAlertRepository->findOpen($document->getKey(), $rule->getKey(), $alertDate);

                    if ($existing) {
                        continue;
                    }

                    $created[] = $this->documentAlertRepository->create([
                        'tenant_id' => $document->tenant_id,
                        'document_id' => $document->getKey(),
                        'alert_rule_id' => $rule->getKey(),
                        'alert_date' => $alertDate,
                        'notification_channel' => $rule->notification_channel,
                        'status' => 'pending',
                        'message' => $this->buildMessage($document),
                    ]);
                }
            }

            return [
                'generated' => count($created),
                'alerts' => $created,
            ];
        });
    }

    public function markAsRead(string $uid): DocumentAlert
    {
        $alert = DocumentAlert::query()->with(['document.account', 'document.documentType', 'alertRule'])->where('uid', $uid)->firstOrFail();
        $alert->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return $alert->fresh(['document.account', 'document.documentType', 'alertRule']);
    }

    private function shouldTrigger(?Carbon $expirationDate, int $daysBefore): bool
    {
        if (!$expirationDate) {
            return false;
        }

        $days = now()->startOfDay()->diffInDays(Carbon::parse($expirationDate)->startOfDay(), false);

        if ($days < 0) {
            return true;
        }

        return $days <= $daysBefore;
    }

    private function buildMessage($document): string
    {
        $typeName = $document->documentType?->name ?? 'Documento';
        $accountName = $document->account?->name ?? 'cliente';

        return match ($document->status) {
            'expired' => "{$typeName} vencido para {$accountName}",
            'expiring' => "{$typeName} por vencer para {$accountName}",
            default => "{$typeName} requiere seguimiento para {$accountName}",
        };
    }
}
