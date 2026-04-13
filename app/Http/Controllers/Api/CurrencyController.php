<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencyService)
    {
    }

    public function rates(Request $request)
    {
        try {
            return $this->successResponse($this->currencyService->rates($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function convert(Request $request)
    {
        try {
            return $this->successResponse($this->currencyService->convert($request->all()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function storeRate(Request $request)
    {
        try {
            return $this->successResponse($this->currencyService->upsertRate($request->all()), 201, 'Tasa de cambio registrada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }
}
