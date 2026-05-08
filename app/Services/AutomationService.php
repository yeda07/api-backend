<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AutomationAssignmentRule;
use App\Models\AutomationRule;
use App\Models\User;
use App\Support\ApiIndex;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AutomationService
{
    private const TRIGGERS = 'lead_created,deal_won,deal_lost,contact_updated,task_completed';
    private const ACTIONS = 'send_email,update_field,create_task,send_webhook';

    public function __construct(private readonly ConditionEvaluator $conditionEvaluator)
    {
    }

    public function listRules(array $filters = [])
    {
        return ApiIndex::paginateOrGet(AutomationRule::query()->latest(), $filters, 'automation_rules_page');
    }

    public function getRule(string $uid): AutomationRule
    {
        return AutomationRule::query()->where('uid', $uid)->firstOrFail();
    }

    public function createRule(array $data): AutomationRule
    {
        return AutomationRule::query()->create($this->validateRule($data))->fresh();
    }

    public function updateRule(string $uid, array $data): AutomationRule
    {
        $rule = $this->getRule($uid);
        $rule->update($this->validateRule($data, true));

        return $rule->fresh();
    }

    public function deleteRule(string $uid): void
    {
        $this->getRule($uid)->delete();
    }

    public function toggleRule(string $uid): AutomationRule
    {
        $rule = $this->getRule($uid);
        $rule->update(['is_active' => !$rule->is_active]);

        return $rule->fresh();
    }

    public function listAssignmentRules(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            AutomationAssignmentRule::query()->with('assignedTo')->latest(),
            $filters,
            'automation_assignment_rules_page'
        );
    }

    public function createAssignmentRule(array $data): AutomationAssignmentRule
    {
        return AutomationAssignmentRule::query()->create($this->validateAssignmentRule($data))->fresh('assignedTo');
    }

    public function updateAssignmentRule(string $uid, array $data): AutomationAssignmentRule
    {
        $rule = AutomationAssignmentRule::query()->with('assignedTo')->where('uid', $uid)->firstOrFail();
        $rule->update($this->validateAssignmentRule($data, true));

        return $rule->fresh('assignedTo');
    }

    public function deleteAssignmentRule(string $uid): void
    {
        AutomationAssignmentRule::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function execute(string $triggerSource, array $payload = []): array
    {
        $results = AutomationRule::query()
            ->where('is_active', true)
            ->where('trigger_source', $triggerSource)
            ->get()
            ->map(function (AutomationRule $rule) use ($payload) {
                $matched = $this->conditionEvaluator->matches($rule->conditions ?? [], $payload, $rule->logic);
                $actions = [];

                if ($matched) {
                    foreach ($rule->actions ?? [] as $action) {
                        $actions[] = $this->executeAction($action, $payload);
                    }

                    $rule->forceFill([
                        'execution_count' => (int) $rule->execution_count + 1,
                        'last_executed_at' => now(),
                    ])->save();
                }

                return [
                    'uid' => $rule->uid,
                    'name' => $rule->name,
                    'matched' => $matched,
                    'actions' => $actions,
                ];
            })
            ->values();

        return [
            'trigger_source' => $triggerSource,
            'evaluated' => $results->count(),
            'executed' => $results->where('matched', true)->count(),
            'results' => $results,
        ];
    }

    public function resolveAssignment(array $payload): ?AutomationAssignmentRule
    {
        return AutomationAssignmentRule::query()
            ->with('assignedTo')
            ->where('is_active', true)
            ->get()
            ->first(fn (AutomationAssignmentRule $rule) => $this->conditionEvaluator->matches($rule->conditions ?? [], $payload, $rule->logic));
    }

    private function validateRule(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'trigger_source' => [$partial ? 'sometimes' : 'required', 'string', 'in:' . self::TRIGGERS],
            'trigger_config' => 'sometimes|array',
            'conditions' => 'sometimes|array',
            'conditions.*.field' => 'required_with:conditions|string|max:255',
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,contains,greater_than,less_than,in,not_in',
            'conditions.*.value' => 'nullable',
            'actions' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'actions.*.type' => 'required_with:actions|string|in:' . self::ACTIONS,
            'actions.*.config' => 'sometimes|array',
            'logic' => 'sometimes|string|in:AND,OR',
            'is_active' => 'sometimes|boolean',
        ])->validate();
    }

    private function validateAssignmentRule(array $data, bool $partial = false): array
    {
        $validated = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'conditions' => 'sometimes|array',
            'conditions.*.field' => 'required_with:conditions|string|max:255',
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,contains,greater_than,less_than,in,not_in',
            'conditions.*.value' => 'nullable',
            'assigned_to_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'assigned_to_name' => 'sometimes|string|max:255',
            'logic' => 'sometimes|string|in:AND,OR',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        if (array_key_exists('assigned_to_uid', $validated)) {
            $validated['assigned_to_user_id'] = $this->resolveUserId($validated['assigned_to_uid']);
            unset($validated['assigned_to_uid'], $validated['assigned_to_name']);
        }

        return $validated;
    }

    private function executeAction(array $action, array $payload): array
    {
        try {
            return match ($action['type'] ?? null) {
                'send_email' => $this->sendEmail($action['config'] ?? []),
                'update_field' => $this->updateField($action['config'] ?? [], $payload),
                'create_task' => $this->createTask($action['config'] ?? [], $payload),
                'send_webhook' => $this->sendWebhook($action['config'] ?? [], $payload),
                default => ['type' => $action['type'] ?? null, 'success' => false],
            };
        } catch (\Throwable $e) {
            Log::warning('Automation action failed', ['error' => $e->getMessage()]);

            return ['type' => $action['type'] ?? null, 'success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendEmail(array $config): array
    {
        if (empty($config['to'])) {
            return ['type' => 'send_email', 'success' => false, 'message' => 'Missing recipient'];
        }

        Mail::raw($config['body'] ?? 'Automation triggered: ' . ($config['template'] ?? 'default'), function ($message) use ($config) {
            $message->to($config['to'])->subject($config['subject'] ?? 'Automation notification');
        });

        return ['type' => 'send_email', 'success' => true];
    }

    private function updateField(array $config, array $payload): array
    {
        $entity = find_entity_by_uid($config['entity_type'] ?? data_get($payload, 'entity_type'), $config['entity_uid'] ?? data_get($payload, 'entity_uid'));

        if (!$entity || empty($config['field'])) {
            return ['type' => 'update_field', 'success' => false];
        }

        $entity->update([$config['field'] => $config['value'] ?? null]);

        return ['type' => 'update_field', 'success' => true];
    }

    private function createTask(array $config, array $payload): array
    {
        $entity = null;

        if (($config['entity_type'] ?? data_get($payload, 'entity_type')) && ($config['entity_uid'] ?? data_get($payload, 'entity_uid'))) {
            $entity = find_entity_by_uid($config['entity_type'] ?? data_get($payload, 'entity_type'), $config['entity_uid'] ?? data_get($payload, 'entity_uid'));
        }

        $activity = Activity::query()->create([
            'assigned_user_id' => !empty($config['assigned_to_uid']) ? $this->resolveUserId($config['assigned_to_uid']) : null,
            'activityable_type' => $entity ? get_class($entity) : null,
            'activityable_id' => $entity?->getKey(),
            'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
            'type' => 'task',
            'title' => $config['title'] ?? 'Automation task',
            'description' => $config['description'] ?? null,
            'status' => 'pending',
            'priority' => $config['priority'] ?? 'medium',
            'scheduled_at' => $config['scheduled_at'] ?? now(),
        ]);

        return ['type' => 'create_task', 'success' => true, 'activity_uid' => $activity->uid];
    }

    private function sendWebhook(array $config, array $payload): array
    {
        if (empty($config['url'])) {
            return ['type' => 'send_webhook', 'success' => false, 'message' => 'Missing URL'];
        }

        Http::timeout(5)->post($config['url'], ['payload' => $payload, 'config' => $config]);

        return ['type' => 'send_webhook', 'success' => true];
    }

    private function resolveUserId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $id = User::query()->where('uid', $uid)->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'assigned_to_uid' => ['El usuario asignado no existe o no pertenece a este tenant'],
            ]);
        }

        return $id;
    }
}
