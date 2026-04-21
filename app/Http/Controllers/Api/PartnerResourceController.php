<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerResourceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerResourceController extends Controller
{
    public function __construct(private readonly PartnerResourceService $partnerResourceService)
    {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->partnerResourceService->resources($request->query()));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:sales,training',
                'partner_uids' => 'sometimes|array',
                'partner_uids.*' => 'uuid',
                'is_active' => 'sometimes|boolean',
                'file' => 'required|file',
            ]);

            return $this->successResponse(
                $this->partnerResourceService->uploadResource($validated, $request->file('file')),
                201,
                'Recurso de partner creado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function assign(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'partner_uids' => 'required|array|min:1',
                'partner_uids.*' => 'uuid',
            ]);

            return $this->successResponse(
                $this->partnerResourceService->assignResourceToPartners($uid, $validated['partner_uids']),
                200,
                'Recurso asignado a partners'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
