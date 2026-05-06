<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teamService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->teamService->list());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->teamService->get($uid));
    }

    public function store(Request $request)
    {
        return $this->wrap(fn () => $this->teamService->create($request->all()), 'Equipo creado', 201);
    }

    public function update(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->teamService->update($uid, $request->all()), 'Equipo actualizado');
    }

    public function destroy(string $uid)
    {
        $this->teamService->delete($uid);

        return $this->successResponse(null, 200, 'Equipo eliminado');
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
