<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpportunityService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OpportunityController extends Controller
{
    public function __construct(private readonly OpportunityService $opportunityService)
    {
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

    public function board()
    {
        return $this->successResponse($this->opportunityService->board());
    }

    public function summary()
    {
        return $this->successResponse($this->opportunityService->summary());
    }
}
