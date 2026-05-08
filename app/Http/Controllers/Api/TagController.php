<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    public function __construct(private readonly TagService $tagService)
    {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->tagService->getAll($request->query()));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'key' => 'sometimes|nullable|string|max:100',
                'color' => 'required|string|max:20',
                'category' => 'nullable|string|max:100',
                'entity_types' => 'nullable|array',
                'entity_types.*' => 'string|in:CONTACT,COMPANY,LEAD,DEAL,contact,company,lead,deal',
            ]);

            return $this->successResponse($this->tagService->create($validated), 201, 'Etiqueta creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'key' => 'sometimes|nullable|string|max:100',
                'color' => 'sometimes|string|max:20',
                'category' => 'sometimes|string|max:100',
                'entity_types' => 'nullable|array',
                'entity_types.*' => 'string|in:CONTACT,COMPANY,LEAD,DEAL,contact,company,lead,deal',
            ]);

            return $this->successResponse($this->tagService->update($uid, $validated), 200, 'Etiqueta actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->tagService->delete($uid);

            return $this->successResponse(null, 200, 'Etiqueta eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function assign(Request $request)
    {
        try {
            $validated = $request->validate([
                'tag_uid' => 'required|uuid',
                'entity_type' => 'required|string',
                'entity_uid' => 'required|uuid',
            ]);

            return $this->successResponse(
                $this->tagService->assignToEntity($validated['tag_uid'], $validated['entity_type'], $validated['entity_uid']),
                200,
                'Etiqueta asignada'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function unassign(Request $request)
    {
        try {
            $validated = $request->validate([
                'tag_uid' => 'required|uuid',
                'entity_type' => 'required|string',
                'entity_uid' => 'required|uuid',
            ]);

            return $this->successResponse(
                $this->tagService->removeFromEntity($validated['tag_uid'], $validated['entity_type'], $validated['entity_uid']),
                200,
                'Etiqueta retirada'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
