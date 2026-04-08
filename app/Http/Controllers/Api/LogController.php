<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = SystemLog::where('tenant_id', $tenantId);

        if ($request->level) {
            $query->where('level', $request->level);
        }

        return $this->successResponse($query->latest()->limit(100)->get());
    }
}
