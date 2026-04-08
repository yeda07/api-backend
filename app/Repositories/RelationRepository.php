<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Relation;

class RelationRepository
{
    public function all()
    {
        return Relation::all();
    }

    public function findByEntity(string $type, int $entityId)
    {
        return Relation::where(function ($q) use ($type, $entityId) {
            $q->where('from_type', $type)
                ->where('from_id', $entityId);
        })
            ->orWhere(function ($q) use ($type, $entityId) {
                $q->where('to_type', $type)
                    ->where('to_id', $entityId);
            })
            ->get();
    }

    public function create(array $data)
    {
        if ($this->existsRelation($data)) {
            throw new \Exception('La relación ya existe');
        }

        return Relation::create($data);
    }

    public function existsRelation(array $data)
    {
        return Relation::where([
            'tenant_id' => $data['tenant_id'] ?? auth()->user()?->tenant_id,
            'from_type' => $data['from_type'],
            'from_id' => $data['from_id'],
            'to_type' => $data['to_type'],
            'to_id' => $data['to_id'],
            'relation_type' => $data['relation_type'],
        ])->exists();
    }

    public function delete(string $uid)
    {
        $relation = Relation::where('uid', $uid)->firstOrFail();
        return $relation->delete();
    }

    public function getWithEntities()
    {
        return Relation::all()->map(function ($rel) {
            $from = $this->resolveModel($rel->from_type, $rel->from_id);
            $to = $this->resolveModel($rel->to_type, $rel->to_id);

            return [
                'uid' => $rel->uid,
                'from_type' => class_basename($rel->from_type),
                'from' => $from?->display_name,
                'from_uid' => $from?->uid,
                'to_type' => class_basename($rel->to_type),
                'to' => $to?->display_name,
                'to_uid' => $to?->uid,
                'relation_type' => $rel->relation_type,
            ];
        });
    }

    public function getHierarchy(string $type, int $entityId)
    {
        $relations = Relation::where('relation_type', 'reports_to')
            ->where(function ($q) use ($type, $entityId) {
                $q->where('from_type', $type)
                    ->where('from_id', $entityId);
            })
            ->orWhere(function ($q) use ($type, $entityId) {
                $q->where('to_type', $type)
                    ->where('to_id', $entityId);
            })
            ->get();

        return $relations->map(function ($rel) {
            $from = $this->resolveModel($rel->from_type, $rel->from_id);
            $to = $this->resolveModel($rel->to_type, $rel->to_id);

            return [
                'employee' => $from?->display_name,
                'employee_uid' => $from?->uid,
                'reports_to' => $to?->display_name,
                'reports_to_uid' => $to?->uid,
            ];
        });
    }

    private function resolveModel(string $type, int $id)
    {
        return match ($type) {
            Account::class => Account::find($id),
            Contact::class => Contact::find($id),
            CrmEntity::class => CrmEntity::find($id),
            default => null,
        };
    }
}
