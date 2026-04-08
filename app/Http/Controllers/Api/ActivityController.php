<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->activityService->getAll());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->activityService->getByUid($uid));
    }

    public function byRange(Request $request)
    {
        try {
            $validated = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            return $this->successResponse($this->activityService->getByDateRange($validated['from'], $validated['to']));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->activityService->create($request->all()), 201, 'Actividad creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->activityService->update($uid, $request->all()), 200, 'Actividad actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->activityService->delete($uid);

            return $this->successResponse(null, 200, 'Actividad eliminada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
