<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index()
    {
        return $this->successResponse(Plan::all());
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'nullable|numeric',
                'max_users' => 'nullable|integer|min:0',
                'max_accounts' => 'nullable|integer|min:0',
                'max_contacts' => 'nullable|integer|min:0',
                'max_entities' => 'nullable|integer|min:0',
                'max_records' => 'nullable|integer|min:0',
            ]);

            return $this->successResponse(Plan::create($validated), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
