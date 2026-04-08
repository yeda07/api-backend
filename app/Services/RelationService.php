<?php

namespace App\Services;

use App\Repositories\RelationRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RelationService
{
    protected $repo;

    public function __construct(RelationRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll()
    {
        return $this->repo->all();
    }

    public function getAllWithEntities()
    {
        return $this->repo->getWithEntities();
    }

    public function getByEntity(string $type, string $uid)
    {
        $resolvedType = $this->resolveEntityType($type);
        $entityId = $this->resolveEntityId($resolvedType, $uid);

        return $this->repo->findByEntity($resolvedType, $entityId);
    }

    public function getHierarchy(string $type, string $uid)
    {
        $resolvedType = $this->resolveEntityType($type);
        $entityId = $this->resolveEntityId($resolvedType, $uid);

        return $this->repo->getHierarchy($resolvedType, $entityId);
    }

    public function create(array $data)
    {
        $this->validate($data);
        $data = $this->normalizeEntityReferences($data);

        try {
            return $this->repo->create($data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'relation' => [$e->getMessage()],
            ]);
        }
    }

    public function delete(string $uid)
    {
        return $this->repo->delete($uid);
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'from_type' => 'required|string',
            'from_uid' => 'required|uuid',
            'from_id' => 'prohibited',
            'to_type' => 'required|string',
            'to_uid' => 'required|uuid',
            'to_id' => 'prohibited',
            'relation_type' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $normalized = $this->normalizeEntityReferences($data);

        if (
            $normalized['from_type'] === $normalized['to_type']
            && $normalized['from_id'] === $normalized['to_id']
        ) {
            throw ValidationException::withMessages([
                'relation' => ['No puedes relacionar una entidad consigo misma'],
            ]);
        }
    }

    private function normalizeEntityReferences(array $data): array
    {
        $data['from_type'] = $this->resolveEntityType($data['from_type'] ?? '');
        $data['to_type'] = $this->resolveEntityType($data['to_type'] ?? '');

        unset($data['from_id'], $data['to_id']);

        if (!empty($data['from_uid'])) {
            $data['from_id'] = $this->resolveEntityId($data['from_type'], $data['from_uid']);
        }

        if (!empty($data['to_uid'])) {
            $data['to_id'] = $this->resolveEntityId($data['to_type'], $data['to_uid']);
        }

        return $data;
    }

    private function resolveEntityType(string $type): string
    {
        $resolvedType = crm_entity_model_class($type);

        if (!$resolvedType) {
            throw ValidationException::withMessages([
                'type' => ['Tipo de entidad no soportado'],
            ]);
        }

        return $resolvedType;
    }

    private function resolveEntityId(string $type, string $uid): int
    {
        $entityId = find_entity_id_by_uid($type, $uid);

        if (!$entityId) {
            throw ValidationException::withMessages([
                'uid' => ['La entidad no existe'],
            ]);
        }

        return $entityId;
    }
}
