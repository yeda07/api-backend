<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->taskService->getAll());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->taskService->getByUid($uid));
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->taskService->create($request->all()), 201, 'Tarea creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->taskService->update($uid, $request->all()), 200, 'Tarea actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->taskService->delete($uid);

            return $this->successResponse(null, 200, 'Tarea eliminada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
