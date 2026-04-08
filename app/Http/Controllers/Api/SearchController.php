<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $searchService)
    {
    }

    public function search(Request $request)
    {
        try {
            return $this->successResponse($this->searchService->search($request->all()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function export(Request $request)
    {
        try {
            $format = $request->input('format', 'json');

            if ($format === 'csv') {
                $csv = $this->searchService->exportAsCsv($request->all());
                $filename = 'segment-export-' . now()->format('Ymd-His') . '.csv';

                return new StreamedResponse(function () use ($csv) {
                    echo $csv;
                }, 200, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }

            return $this->successResponse($this->searchService->export($request->all()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
