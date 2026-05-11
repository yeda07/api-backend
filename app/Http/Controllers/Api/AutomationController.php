<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutomationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AutomationController extends Controller
{
    public function __construct(private readonly AutomationService $automationService)
    {
    }

    public function rules(Request $request)
    {
        return $this->successResponse($this->automationService->listRules($request->query()));
    }

    public function triggerEvents()
    {
        return $this->successResponse($this->automationService->triggerEvents());
    }

    public function actions()
    {
        return $this->successResponse($this->automationService->actions());
    }

    public function showRule(string $uid)
    {
        return $this->successResponse($this->automationService->getRule($uid));
    }

    public function storeRule(Request $request)
    {
        return $this->wrap(fn () => $this->automationService->createRule($request->all()), 'Rule created', 201);
    }

    public function updateRule(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->automationService->updateRule($uid, $request->all()), 'Rule updated');
    }

    public function destroyRule(string $uid)
    {
        $this->automationService->deleteRule($uid);

        return $this->successResponse(null, 200, 'Rule deleted');
    }

    public function toggleRule(string $uid)
    {
        return $this->successResponse($this->automationService->toggleRule($uid), 200, 'Rule toggled');
    }

    public function assignmentRules(Request $request)
    {
        return $this->successResponse($this->automationService->listAssignmentRules($request->query()));
    }

    public function storeAssignmentRule(Request $request)
    {
        return $this->wrap(fn () => $this->automationService->createAssignmentRule($request->all()), 'Assignment rule created', 201);
    }

    public function updateAssignmentRule(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->automationService->updateAssignmentRule($uid, $request->all()), 'Assignment rule updated');
    }

    public function destroyAssignmentRule(string $uid)
    {
        $this->automationService->deleteAssignmentRule($uid);

        return $this->successResponse(null, 200, 'Assignment rule deleted');
    }

    private function wrap(\Closure $callback, string $message, int $status = 200)
    {
        try {
            return $this->successResponse($callback(), $status, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }
}
