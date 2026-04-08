<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Validation\ValidationException;

class TagService
{
    public function getAll()
    {
        return Tag::query()->orderBy('category')->orderBy('name')->get();
    }

    public function create(array $data): Tag
    {
        $this->ensureUniqueKey($data['key']);

        return Tag::query()->create([
            'name' => $data['name'],
            'key' => $data['key'],
            'color' => $data['color'],
            'category' => $data['category'] ?? 'general',
        ]);
    }

    public function update(string $uid, array $data): Tag
    {
        $tag = $this->findTag($uid);

        if (isset($data['key']) && $data['key'] !== $tag->key) {
            $this->ensureUniqueKey($data['key'], $tag->getKey());
        }

        $tag->update([
            'name' => $data['name'] ?? $tag->name,
            'key' => $data['key'] ?? $tag->key,
            'color' => $data['color'] ?? $tag->color,
            'category' => $data['category'] ?? $tag->category,
        ]);

        return $tag->fresh();
    }

    public function delete(string $uid): void
    {
        $this->findTag($uid)->delete();
    }

    public function assignToEntity(string $tagUid, string $entityType, string $entityUid): array
    {
        $tag = $this->findTag($tagUid);
        $entity = $this->findEntity($entityType, $entityUid);

        $entity->tags()->syncWithoutDetaching([$tag->getKey()]);

        return [
            'tag' => $tag->fresh(),
            'entity_uid' => $entity->uid,
            'entity_type' => get_class($entity),
        ];
    }

    public function removeFromEntity(string $tagUid, string $entityType, string $entityUid): array
    {
        $tag = $this->findTag($tagUid);
        $entity = $this->findEntity($entityType, $entityUid);

        $entity->tags()->detach($tag->getKey());

        return [
            'tag' => $tag->fresh(),
            'entity_uid' => $entity->uid,
            'entity_type' => get_class($entity),
        ];
    }

    private function findTag(string $uid): Tag
    {
        $tag = Tag::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('uid', $uid)
            ->first();

        if (!$tag) {
            throw ValidationException::withMessages([
                'tag_uid' => ['La etiqueta no existe o no pertenece a este tenant'],
            ]);
        }

        return $tag;
    }

    private function ensureUniqueKey(string $key, ?int $ignoreId = null): void
    {
        $exists = Tag::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('key', $key)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => ['Ya existe una etiqueta con esta clave en el tenant'],
            ]);
        }
    }

    private function findEntity(string $entityType, string $entityUid)
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
