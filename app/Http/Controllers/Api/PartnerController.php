<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerOpportunityService;
use App\Services\PartnerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService,
        private readonly PartnerOpportunityService $partnerOpportunityService
    ) {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->partnerService->getPartners($request->query()));
    }

    public function store(Request $request)
    {
        return $this->wrap(fn () => $this->partnerService->createPartner($request->all()), 'Partner creado', 201);
    }

    public function update(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->partnerService->updatePartner($uid, $request->all()), 'Partner actualizado');
    }

    public function opportunities(Request $request)
    {
        return $this->successResponse($this->partnerOpportunityService->opportunities($request->query()));
    }

    public function storeOpportunity(Request $request)
    {
        return $this->wrap(fn () => $this->partnerOpportunityService->createOpportunity($request->all()), 'Oportunidad de partner creada', 201);
    }

    public function showOpportunity(string $uid)
    {
        return $this->successResponse($this->partnerOpportunityService->getOpportunity($uid));
    }

    public function validateOpportunity(Request $request)
    {
        return $this->wrap(fn () => $this->partnerOpportunityService->checkConflict($request->all()));
    }

    public function closeOpportunity(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->partnerOpportunityService->closeOpportunity($uid, $request->all()), 'Oportunidad de partner cerrada');
    }

    private function wrap(\Closure $callback, ?string $message = null, int $status = 200)
    {
        try {
            return $this->successResponse($callback(), $status, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
