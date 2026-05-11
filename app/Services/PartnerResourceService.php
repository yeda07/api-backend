<?php

namespace App\Services;

use App\Models\PartnerResource;
use App\Repositories\PartnerResourceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PartnerResourceService
{
    private const TYPES = [
        'sales' => 'Ventas',
        'training' => 'Capacitacion',
    ];

    public function __construct(
        private readonly PartnerResourceRepository $partnerResourceRepository,
        private readonly PartnerService $partnerService
    ) {}

    public function types(): array
    {
        return $this->options(self::TYPES);
    }

    public function resources(array $filters = [])
    {
        $filters = $this->normalizeResourcePayload($filters);
        $validated = Validator::make($filters, [
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:sales,training',
            'partner_uid' => 'nullable|uuid',
            'is_active' => 'nullable',
        ])->validate();

        return $this->partnerResourceRepository->all(array_merge($filters, $validated));
    }

    public function uploadResource(array $data, UploadedFile $file): PartnerResource
    {
        $data = $this->normalizeResourcePayload($data);
        $validated = Validator::make($data, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:sales,training',
            'partner_uids' => 'sometimes|array',
            'partner_uids.*' => 'uuid',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $disk = config('filesystems.default', 'local');
        $path = $file->store('partner-resources/'.auth()->user()->tenant_id, $disk);

        $resource = $this->partnerResourceRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'disk' => $disk,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['partner_uids'])) {
            $this->assignResourceToPartners($resource->uid, $validated['partner_uids']);
        }

        return $resource->fresh('partners');
    }

    public function updateResource(string $resourceUid, array $data): PartnerResource
    {
        $data = $this->normalizeResourcePayload($data);
        $validated = Validator::make($data, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:sales,training',
            'partner_uids' => 'sometimes|array',
            'partner_uids.*' => 'uuid',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $resource = $this->partnerResourceRepository->findByUid($resourceUid);

        return DB::transaction(function () use ($resource, $validated) {
            $payload = collect($validated)
                ->only(['title', 'description', 'type', 'is_active'])
                ->all();

            if ($payload !== []) {
                $resource->update($payload);
            }

            if (array_key_exists('partner_uids', $validated)) {
                $partnerIds = collect($validated['partner_uids'])
                    ->map(fn (string $uid) => $this->partnerService->getPartnerByUid($uid)->getKey())
                    ->all();
                $resource->partners()->sync($partnerIds);
            }

            return $resource->fresh('partners');
        });
    }

    public function deleteResource(string $resourceUid): void
    {
        $resource = $this->partnerResourceRepository->findByUid($resourceUid);

        DB::transaction(function () use ($resource) {
            $disk = $resource->disk ?: config('filesystems.default', 'local');
            $path = $resource->file_path;

            $resource->partners()->detach();
            $resource->delete();

            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        });
    }

    public function downloadResource(string $resourceUid)
    {
        $resource = $this->partnerResourceRepository->findByUid($resourceUid);
        $disk = $resource->disk ?: config('filesystems.default', 'local');

        if (! Storage::disk($disk)->exists($resource->file_path)) {
            throw ValidationException::withMessages([
                'file' => ['El archivo no existe o no esta disponible'],
            ]);
        }

        $resource->increment('download_count');

        return Storage::disk($disk)->download(
            $resource->file_path,
            $resource->original_name,
            ['Content-Type' => $resource->mime_type ?: 'application/octet-stream']
        );
    }

    public function assignResourceToPartners(string $resourceUid, array $partnerUids): PartnerResource
    {
        $resource = $this->partnerResourceRepository->findByUid($resourceUid);
        $partnerIds = collect($partnerUids)->map(function (string $uid) {
            $partner = $this->partnerService->getPartnerByUid($uid);

            return $partner->getKey();
        })->all();

        $resource->partners()->syncWithoutDetaching($partnerIds);

        return $resource->fresh('partners');
    }

    public function getResourcesByPartner(string $partnerUid)
    {
        $partner = $this->partnerService->getPartnerByUid($partnerUid);

        return $partner->resources()->where('is_active', true)->get();
    }

    private function normalizeResourcePayload(array $data): array
    {
        if (array_key_exists('material_type', $data) && ! array_key_exists('type', $data)) {
            $data['type'] = match ($data['material_type']) {
                'training', 'guide' => 'training',
                default => 'sales',
            };
        }

        unset($data['material_type'], $data['file_name'], $data['file_size'], $data['uploaded_at'], $data['uploaded_by'], $data['tags'], $data['download_count']);

        return $data;
    }

    private function options(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => [
                'uid' => $this->stableUid($value),
                'key' => $value,
                'name' => $label,
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function stableUid(string $key): string
    {
        $hash = md5('partner-resource:'.$key);

        return substr($hash, 0, 8)
            .'-'.substr($hash, 8, 4)
            .'-'.substr($hash, 12, 4)
            .'-'.substr($hash, 16, 4)
            .'-'.substr($hash, 20, 12);
    }
}
