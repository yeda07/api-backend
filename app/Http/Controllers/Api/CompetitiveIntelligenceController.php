<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompetitiveIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetitiveIntelligenceController extends Controller
{
    public function __construct(private readonly CompetitiveIntelligenceService $service)
    {
    }

    public function competitors()
    {
        return $this->successResponse($this->service->competitors());
    }

    public function storeCompetitor(Request $request)
    {
        try {
            return $this->successResponse($this->service->createCompetitor($request->all()), 201, 'Competidor creado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateCompetitor(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->service->updateCompetitor($uid, $request->all()), 200, 'Competidor actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyCompetitor(string $uid)
    {
        try {
            $this->service->deleteCompetitor($uid);

            return $this->successResponse(null, 200, 'Competidor eliminado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function battlecards(Request $request)
    {
        return $this->successResponse($this->service->battlecards($request->query()));
    }

    public function battlecardsByCompetitor(string $uid)
    {
        return $this->successResponse($this->service->battlecards(['competitor_uid' => $uid]));
    }

    public function storeBattlecard(Request $request)
    {
        try {
            return $this->successResponse($this->service->createBattlecard($request->all()), 201, 'Battlecard creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateBattlecard(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->service->updateBattlecard($uid, $request->all()), 200, 'Battlecard actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyBattlecard(string $uid)
    {
        try {
            $this->service->deleteBattlecard($uid);

            return $this->successResponse(null, 200, 'Battlecard eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function lostReasons(Request $request)
    {
        return $this->successResponse($this->service->lostReasons($request->query()));
    }

    public function storeLostReason(Request $request)
    {
        try {
            return $this->successResponse($this->service->createLostReason($request->all()), 201, 'Lost reason registrada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateLostReason(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->service->updateLostReason($uid, $request->all()), 200, 'Lost reason actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyLostReason(string $uid)
    {
        try {
            $this->service->deleteLostReason($uid);

            return $this->successResponse(null, 200, 'Lost reason eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function lostReasonsReport(Request $request)
    {
        return $this->successResponse($this->service->lostReasonsReport($request->query()));
    }
}
