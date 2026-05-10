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

    public function index(Request $request)
    {
        return $this->successResponse($this->teamService->list($request->query()));
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
        return $this->wrap(function () use ($uid) {
            $this->teamService->delete($uid);

            return null;
        }, 'Team deleted');
    }

    public function addMember(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->teamService->addMember($uid, $request->all()), 'Miembro agregado');
    }

    public function removeMember(string $uid, string $userUid)
    {
        return $this->wrap(function () use ($uid, $userUid) {
            $this->teamService->removeMember($uid, $userUid);

            return null;
        }, 'Member removed');
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
