<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionService $commissionService) {}

    public function plans(Request $request)
    {
        return $this->successResponse($this->commissionService->plans($request->query()));
    }

    public function showPlan(string $uid)
    {
        return $this->successResponse($this->commissionService->getPlan($uid));
    }

    public function storePlan(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->createPlan($request->all()), 'Plan de comision creado', 201);
    }

    public function updatePlan(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->updatePlan($uid, $request->all()), 'Plan de comision actualizado');
    }

    public function destroyPlan(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->commissionService->deletePlan($uid);

            return null;
        }, 'Plan de comision eliminado');
    }

    public function assignments(Request $request)
    {
        return $this->successResponse($this->commissionService->assignments($request->query()));
    }

    public function showAssignment(string $uid)
    {
        return $this->successResponse($this->commissionService->getAssignment($uid));
    }

    public function storeAssignment(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->createAssignment($request->all()), 'Asignacion de comision creada', 201);
    }

    public function storeBulkAssignments(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->createBulkAssignments($request->all()), 'Asignaciones de comision creadas', 201);
    }

    public function updateAssignment(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->updateAssignment($uid, $request->all()), 'Asignacion de comision actualizada');
    }

    public function destroyAssignment(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->commissionService->deleteAssignment($uid);

            return null;
        }, 'Asignacion de comision eliminada');
    }

    public function targets(Request $request)
    {
        return $this->successResponse($this->commissionService->targets($request->query('user_uid'), $request->query()));
    }

    public function showTarget(string $uid)
    {
        return $this->successResponse($this->commissionService->getTarget($uid));
    }

    public function storeTarget(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->upsertTarget($request->all()), 'Meta de comision guardada', 201);
    }

    public function updateTarget(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->updateTarget($uid, $request->all()), 'Meta de comision actualizada');
    }

    public function destroyTarget(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->commissionService->deleteTarget($uid);

            return null;
        }, 'Meta de comision eliminada');
    }

    public function rules(Request $request)
    {
        return $this->successResponse($this->commissionService->rules($request->query()));
    }

    public function storeRule(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->createRule($request->all()), 'Regla de comision creada', 201);
    }

    public function updateRule(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->updateRule($uid, $request->all()), 'Regla de comision actualizada');
    }

    public function destroyRule(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->commissionService->deleteRule($uid);

            return null;
        }, 'Regla de comision eliminada');
    }

    public function recordFinancialEvent(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->recordFinancialEvent($request->all()), 'Recaudo registrado', 201);
    }

    public function entries(Request $request)
    {
        return $this->successResponse($this->commissionService->entries($request->query('user_uid'), $request->query()));
    }

    public function payEntry(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->payEntry($uid, $request->input('paid_at')), 'Comision marcada como pagada');
    }

    public function mySummary()
    {
        return $this->successResponse($this->commissionService->mySummary());
    }

    public function dashboard(string $userUid)
    {
        return $this->successResponse($this->commissionService->dashboard($userUid));
    }

    public function simulate(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->simulate($request->all()));
    }

    public function runs(Request $request)
    {
        return $this->successResponse($this->commissionService->runs($request->query()));
    }

    public function periods()
    {
        return $this->successResponse($this->commissionService->periods());
    }

    public function historyPdf(Request $request)
    {
        return response($this->commissionService->historyPdf($request->query()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="commission-history.pdf"',
        ]);
    }

    public function storeRun(Request $request)
    {
        return $this->wrap(fn () => $this->commissionService->createRun($request->all()), 'Liquidacion de comision creada', 201);
    }

    public function approveRun(string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->approveRun($uid), 'Liquidacion aprobada');
    }

    public function payRun(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->commissionService->payRun($uid, $request->input('paid_at')), 'Liquidacion pagada');
    }

    private function wrap(\Closure $callback, ?string $message = null, int $status = 200)
    {
        try {
            return $this->successResponse($callback(), $status, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
