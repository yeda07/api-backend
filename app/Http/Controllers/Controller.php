<?php

namespace App\Http\Controllers;

use App\Support\ApiIndex;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function successResponse(mixed $data = null, int $status = 200, ?string $message = null)
    {
        $meta = null;

        if ($data instanceof LengthAwarePaginator) {
            $meta = ApiIndex::meta($data);
            $data = $data->items();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 400, ?array $errors = null, mixed $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'meta' => null,
            'errors' => $errors,
        ], $status);
    }
}
