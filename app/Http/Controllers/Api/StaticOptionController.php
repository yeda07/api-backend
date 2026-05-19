<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class StaticOptionController extends Controller
{
    public function projectStatuses()
    {
        return $this->successResponse($this->options([
            'pending' => 'Pendiente',
            'planning' => 'Planeacion',
            'active' => 'Activo',
            'in_progress' => 'En progreso',
            'on_hold' => 'En espera',
            'paused' => 'Pausado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ]));
    }

    public function milestoneStatuses()
    {
        return $this->successResponse($this->options([
            'pending' => 'Pendiente',
            'in_progress' => 'En progreso',
            'done' => 'Hecho',
            'completed' => 'Completado',
        ]));
    }

    public function invoiceStatuses()
    {
        return $this->successResponse($this->options([
            'draft' => 'Borrador',
            'issued' => 'Emitida',
            'partial' => 'Parcial',
            'paid' => 'Pagada',
            'overdue' => 'Vencida',
        ]));
    }

    public function quotationStatuses()
    {
        return $this->successResponse($this->options([
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'approved' => 'Aprobada',
            'invoiced' => 'Facturada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
        ]));
    }

    public function taskStatuses()
    {
        return $this->successResponse($this->options([
            'pending' => 'Pendiente',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ]));
    }

    public function taskPriorities()
    {
        return $this->successResponse($this->options([
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ]));
    }

    public function userStatuses()
    {
        return $this->successResponse($this->options([
            'ACTIVO' => 'Activo',
            'INACTIVO' => 'Inactivo',
        ]));
    }

    private function options(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
