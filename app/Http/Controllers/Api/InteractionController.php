<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InteractionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InteractionController extends Controller
{
    public function __construct(private readonly InteractionService $interactionService)
    {
    }

    public function timeline(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->interactionService->timeline($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function note(Request $request)
    {
        return $this->storeTypedInteraction($request, 'note', 'Nota registrada');
    }

    public function call(Request $request)
    {
        return $this->storeTypedInteraction($request, 'call', 'Llamada registrada');
    }

    public function email(Request $request)
    {
        return $this->storeTypedInteraction($request, 'email', 'Correo registrado');
    }

    private function storeTypedInteraction(Request $request, string $type, string $message)
    {
        try {
            $validated = $request->validate([
                'entity_type' => 'required|string',
                'entity_uid' => 'required|uuid',
                'subject' => 'nullable|string|max:255',
                'content' => 'nullable|string',
                'meta' => 'nullable|array',
                'occurred_at' => 'nullable|date',
            ]);

            return $this->successResponse($this->interactionService->create($type, $validated), 201, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
