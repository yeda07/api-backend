<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function successResponse(mixed $data = null, int $status = 200, ?string $message = null)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 400, ?array $errors = null, mixed $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $status);
    }
}
