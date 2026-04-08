<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;

class MetricsController extends Controller
{
    public function myUsage()
    {
        $tenant = auth()->user()->tenant;

        return $this->successResponse(
            MetricsService::getTenantUsageWithLimits($tenant)
        );
    }
}
