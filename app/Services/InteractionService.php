<?php

namespace App\Services;

use App\Models\Interaction;
use Illuminate\Validation\ValidationException;

class InteractionService
{
    public function timeline(string $entityType, string $entityUid)
    {
        $entity = $this->resolveEntity($entityType, $entityUid);

        return Interaction::query()
            ->where('interactable_type', get_class($entity))
            ->where('interactable_id', $entity->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(string $type, array $data): Interaction
    {
        $entity = $this->resolveEntity($data['entity_type'], $data['entity_uid']);

        return Interaction::query()->create([
            'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
            'actor_user_id' => auth()->id(),
            'type' => $type,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'] ?? null,
            'meta' => $data['meta'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'interactable_type' => get_class($entity),
            'interactable_id' => $entity->getKey(),
        ])->fresh(['owner', 'actor', 'interactable']);
    }

    public function recordStatusChange(object $entity, string $fromStatus, string $toStatus, array $meta = []): ?Interaction
    {
        if (empty($entity->uid) || !method_exists($entity, 'getKey')) {
            return null;
        }

        return Interaction::query()->create([
            'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
            'actor_user_id' => auth()->id(),
            'type' => 'status_change',
            'subject' => 'Cambio de estado',
            'content' => "Estado actualizado de {$fromStatus} a {$toStatus}",
            'meta' => array_merge($meta, [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ]),
            'occurred_at' => now(),
            'interactable_type' => get_class($entity),
            'interactable_id' => $entity->getKey(),
        ]);
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
}
