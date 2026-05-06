<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SegmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segmentService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->segmentService->list());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->segmentService->get($uid));
    }

    public function store(Request $request)
    {
        return $this->wrap(fn () => $this->segmentService->create($request->all()), 'Segmento creado', 201);
    }

    public function update(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->segmentService->update($uid, $request->all()), 'Segmento actualizado');
    }

    public function destroy(string $uid)
    {
        $this->segmentService->delete($uid);

        return $this->successResponse(null, 200, 'Segmento eliminado');
    }

    public function run(string $uid)
    {
        return $this->successResponse($this->segmentService->run($uid));
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
