<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        return $this->successResponse($this->userService->getAll());
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            return $this->successResponse($this->userService->create($validated), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function show(string $uid)
    {
        $user = $this->userService->findByUid($uid);

        if (!$user) {
            return $this->errorResponse('Usuario no encontrado', 404);
        }

        return $this->successResponse($user);
    }

    public function update(Request $request, string $uid)
    {
        try {
            $currentUser = $this->userService->findByUid($uid);

            if (!$currentUser) {
                return $this->errorResponse('Usuario no encontrado', 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($currentUser->id)],
                'password' => 'sometimes|string|min:6',
            ]);

            return $this->successResponse(
                $this->userService->update($uid, $validated),
                200,
                'Usuario actualizado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $deleted = $this->userService->delete($uid);

            if (!$deleted) {
                return $this->errorResponse('Usuario no encontrado', 404);
            }

            return $this->successResponse(null, 200, 'Usuario eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
