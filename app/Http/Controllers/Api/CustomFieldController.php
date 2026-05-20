<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomFieldService;
use App\Support\ApiIndex;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomFieldController extends Controller
{
    protected $service;

    public function __construct(CustomFieldService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $result = $this->service->listFields($request->query());
            $items = $result['items'];
            $meta = [
                'total' => $items instanceof LengthAwarePaginator ? $items->total() : $items->count(),
                'totals' => $result['totals'],
            ];
            $items = $this->service->serializeFields($items);

            if ($items instanceof LengthAwarePaginator) {
                $meta = array_merge(ApiIndex::meta($items), $meta);
                $items = $items->items();
            }

            return response()->json([
                'success' => true,
                'message' => null,
                'data' => $items,
                'meta' => $meta,
                'errors' => null,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function modules()
    {
        return $this->successResponse($this->service->modules());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->service->serializeField($this->service->createField($request->all())), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function assign(Request $request)
    {
        try {
            $validated = $request->validate([
                'entity_type' => 'required|string',
                'entity_uid' => 'required|uuid',
                'custom_field_uid' => 'required|uuid',
                'value' => 'nullable',
                'entity_id' => 'prohibited',
                'custom_field_id' => 'prohibited',
            ]);

            return $this->successResponse($this->service->assignValue(
                $validated['entity_type'],
                $validated['entity_uid'],
                $validated['custom_field_uid'],
                $validated['value'] ?? null
            ));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->service->serializeField($this->service->updateField($uid, $request->all())), 200, 'Campo actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->service->deleteField($uid);

            return $this->successResponse(null, 200, 'Campo eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
