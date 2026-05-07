<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantOptionService;

class TenantOptionController extends Controller
{
    public function __construct(private readonly TenantOptionService $tenantOptionService)
    {
    }

    public function paymentMethods()
    {
        return $this->successResponse($this->tenantOptionService->paymentMethods());
    }

    public function leadOrigins()
    {
        return $this->successResponse($this->tenantOptionService->leadOrigins());
    }

    public function institutionTypes()
    {
        return $this->successResponse($this->tenantOptionService->institutionTypes());
    }

    public function companySizes()
    {
        return $this->successResponse($this->tenantOptionService->companySizes());
    }

    public function industries()
    {
        return $this->successResponse($this->tenantOptionService->industries());
    }

    public function opportunityProducts()
    {
        return $this->successResponse($this->tenantOptionService->opportunityProducts());
    }

    public function lostReasonCategories()
    {
        return $this->successResponse($this->tenantOptionService->lostReasonCategories());
    }

    public function activityTypes()
    {
        return $this->successResponse($this->tenantOptionService->activityTypes());
    }

    public function commissionPlanTypes()
    {
        return $this->successResponse($this->tenantOptionService->commissionPlanTypes());
    }
}
