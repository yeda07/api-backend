<?php

namespace App\Repositories;

use App\Models\Project;
use App\Support\ApiIndex;

class ProjectRepository
{
    public function query()
    {
        return Project::query()->with(['account', 'opportunity.stage', 'invoice', 'assignedUser', 'milestones', 'assignments.user']);
    }

    public function all(array $filters = [])
    {
        $query = $this->query()->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $search = '%' . mb_strtolower($filters['search']) . '%';
            $query->whereRaw('LOWER(name) LIKE ?', [$search]);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', $filters['opportunity_id']);
        }

        if (!empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        return ApiIndex::paginateOrGet($query, $filters, 'projects_page');
    }

    public function findByUid(string $uid): Project
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function findByOpportunityId(int $opportunityId): ?Project
    {
        return $this->query()->where('opportunity_id', $opportunityId)->first();
    }

    public function findByInvoiceId(int $invoiceId): ?Project
    {
        return $this->query()->where('invoice_id', $invoiceId)->first();
    }

    public function create(array $data): Project
    {
        return Project::query()->create($data)->fresh(['account', 'opportunity.stage', 'invoice', 'assignedUser', 'milestones', 'assignments.user']);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh(['account', 'opportunity.stage', 'invoice', 'assignedUser', 'milestones', 'assignments.user']);
    }
}
