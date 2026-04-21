<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerResource;
use App\Repositories\PartnerResourceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PartnerResourceService
{
    public function __construct(
        private readonly PartnerResourceRepository $partnerResourceRepository,
        private readonly PartnerService $partnerService
    ) {
    }

    public function resources(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'type' => 'nullable|string|in:sales,training',
            'partner_uid' => 'nullable|uuid',
            'is_active' => 'nullable',
        ])->validate();

        return $this->partnerResourceRepository->all($validated);
    }

    public function uploadResource(array $data, UploadedFile $file): PartnerResource
    {
        $validated = Validator::make($data, [
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:sales,training',
            'partner_uids' => 'sometimes|array',
            'partner_uids.*' => 'uuid',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $disk = config('filesystems.default', 'local');
        $path = $file->store('partner-resources/' . auth()->user()->tenant_id, $disk);

        $resource = $this->partnerResourceRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'disk' => $disk,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['partner_uids'])) {
            $this->assignResourceToPartners($resource->uid, $validated['partner_uids']);
        }

        return $resource->fresh('partners');
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
}
