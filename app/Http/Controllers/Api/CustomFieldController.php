<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomFieldController extends Controller
{
    protected $service;

    public function __construct(CustomFieldService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->service->createField($request->all()), 201);
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
}
