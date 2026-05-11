<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use App\Services\OpportunityService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly OpportunityService $opportunityService,
        private readonly ActivityService $activityService
    ) {
    }

    public function stages()
    {
        return $this->successResponse($this->opportunityService->stages());
    }

    public function storeStage(Request $request)
    {
        try {
            return $this->successResponse($this->opportunityService->createStage($request->all()), 201, 'Etapa creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateStage(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->opportunityService->updateStage($uid, $request->all()), 200, 'Etapa actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyStage(string $uid)
    {
        try {
            $this->opportunityService->deleteStage($uid);

            return $this->successResponse(null, 200, 'Etapa eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->opportunityService->opportunities($request->query()));
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->opportunityService->createOpportunity($request->all()), 201, 'Oportunidad creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->opportunityService->getOpportunity($uid));
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->opportunityService->updateOpportunity($uid, $request->all()), 200, 'Oportunidad actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->opportunityService->deleteOpportunity($uid);

            return $this->successResponse(null, 200, 'Oportunidad eliminada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function activities(Request $request, string $uid)
    {
        return $this->successResponse($this->activityService->getAll(array_merge($request->query(), [
            'entity_type' => 'opportunity',
            'entity_uid' => $uid,
        ])));
    }

    public function storeActivity(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->activityService->create(array_merge($request->all(), [
                'entity_type' => 'opportunity',
                'entity_uid' => $uid,
            ])), 201, 'Actividad creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateActivity(Request $request, string $uid, string $activityUid)
    {
        try {
            $activity = $this->activityService->getByUid($activityUid);

            if ($activity->activityable_uid !== $uid || !($activity->activityable instanceof \App\Models\Opportunity)) {
                return $this->errorResponse('Actividad no pertenece a esta oportunidad', 404);
            }

            return $this->successResponse($this->activityService->update($activityUid, $request->all()), 200, 'Actividad actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyActivity(string $uid, string $activityUid)
    {
        try {
            $activity = $this->activityService->getByUid($activityUid);

            if ($activity->activityable_uid !== $uid || !($activity->activityable instanceof \App\Models\Opportunity)) {
                return $this->errorResponse('Actividad no pertenece a esta oportunidad', 404);
            }

            $this->activityService->delete($activityUid);

            return $this->successResponse(null, 200, 'Actividad eliminada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function board(Request $request)
    {
        return $this->successResponse($this->opportunityService->board($request->query()));
    }

    public function summary()
    {
        return $this->successResponse($this->opportunityService->summary());
    }
}
