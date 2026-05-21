<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Relation;
use App\Support\ApiIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RelationRepository
{
    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet(Relation::query()->with(['from', 'to']), $filters, 'relations_page');
    }

    public function findByEntity(string $type, int $entityId)
    {
        return Relation::with(['from', 'to'])
            ->where(function ($query) use ($type, $entityId) {
                $query->where(function ($fromQuery) use ($type, $entityId) {
                    $fromQuery->where('from_type', $type)
                        ->where('from_id', $entityId);
                })->orWhere(function ($toQuery) use ($type, $entityId) {
                    $toQuery->where('to_type', $type)
                        ->where('to_id', $entityId);
                });
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

    public function deleteByPair(string $parentUid, string $childUid, ?string $relationType = null): Relation
    {
        $fromCandidates = $this->resolveEntityCandidatesByUid($parentUid);
        $toCandidates = $this->resolveEntityCandidatesByUid($childUid);

        if (empty($fromCandidates) || empty($toCandidates)) {
            throw new ModelNotFoundException('Relacion no encontrada');
        }

        $relation = Relation::query()
            ->with(['from', 'to'])
            ->when($relationType, fn ($query) => $query->where('relation_type', $relationType))
            ->where(function ($query) use ($fromCandidates, $toCandidates) {
                foreach ($fromCandidates as $from) {
                    foreach ($toCandidates as $to) {
                        $query->orWhere(function ($pairQuery) use ($from, $to) {
                            $pairQuery
                                ->where('from_type', $from::class)
                                ->where('from_id', $from->getKey())
                                ->where('to_type', $to::class)
                                ->where('to_id', $to->getKey());
                        });
                    }
                }
            })
            ->first();

        if (!$relation) {
            throw new ModelNotFoundException('Relacion no encontrada');
        }

        $relation->delete();

        return $relation;
    }

    public function getWithEntities(array $filters = [])
    {
        $result = ApiIndex::paginateOrGet(Relation::query()->with(['from', 'to']), $filters, 'relations_page');
        $mapper = function ($rel) {
            $from = $rel->from;
            $to = $rel->to;

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
        };

        return method_exists($result, 'through')
            ? $result->through($mapper)
            : $result->map($mapper);
    }

    public function getHierarchy(string $type, int $entityId)
    {
        $relations = Relation::with(['from', 'to'])
            ->where('relation_type', 'reports_to')
            ->where(function ($q) use ($type, $entityId) {
                $q->where(function ($fromQuery) use ($type, $entityId) {
                    $fromQuery->where('from_type', $type)
                        ->where('from_id', $entityId);
                })->orWhere(function ($toQuery) use ($type, $entityId) {
                    $toQuery->where('to_type', $type)
                        ->where('to_id', $entityId);
                });
            })
            ->get();

        return $relations->map(function ($rel) {
            $from = $rel->from;
            $to = $rel->to;

            return [
                'employee' => $from?->display_name,
                'employee_uid' => $from?->uid,
                'reports_to' => $to?->display_name,
                'reports_to_uid' => $to?->uid,
            ];
        });
    }

    /**
     * @return array<int, Model>
     */
    private function resolveEntityCandidatesByUid(string $uid): array
    {
        return collect($this->supportedModelClasses())
            ->map(fn (string $modelClass) => $modelClass::query()->where('uid', $uid)->first())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function supportedModelClasses(): array
    {
        return [
            Account::class,
            Contact::class,
            CrmEntity::class,
            Opportunity::class,
            Product::class,
        ];
    }

}
