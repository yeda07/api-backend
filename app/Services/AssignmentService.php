<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectAssignmentRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    private const ROLES = [
        'consultant' => 'Consultor',
        'tech' => 'Tecnico',
        'manager' => 'Manager',
        'developer' => 'Desarrollador',
        'designer' => 'Disenador',
        'qa' => 'QA',
        'analyst' => 'Analista',
    ];

    public function __construct(private readonly ProjectAssignmentRepository $projectAssignmentRepository)
    {
    }

    public function roles(): array
    {
        return collect(self::ROLES)
            ->map(fn (string $label, string $value) => [
                'uid' => $this->stableUid($value),
                'key' => $value,
                'value' => $value,
                'name' => $label,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public function assignUser(string $projectUid, array $data)
    {
        $project = Project::query()->where('uid', $projectUid)->firstOrFail();
        $validated = $this->validate($data);
        $user = $this->resolveUser($validated['user_uid']);

        if ($this->projectAssignmentRepository->existsForProjectAndUser($project->getKey(), $user->getKey())) {
            throw ValidationException::withMessages([
                'user_uid' => ['El usuario ya esta asignado a este proyecto'],
            ]);
        }

        return $this->projectAssignmentRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'project_id' => $project->getKey(),
            'user_id' => $user->getKey(),
            'role' => $validated['role'],
            'hours_allocated' => $validated['hours_allocated'],
        ]);
    }

    public function removeUser(string $assignmentUid): void
    {
        $assignment = $this->projectAssignmentRepository->findByUid($assignmentUid);
        $this->projectAssignmentRepository->delete($assignment);
    }

    public function removeProjectAssignment(string $projectUid, string $assignmentUid): void
    {
        $project = Project::query()->where('uid', $projectUid)->firstOrFail();
        $assignment = $this->projectAssignmentRepository->findByUid($assignmentUid);

        if ((int) $assignment->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages([
                'assignment_uid' => ['El recurso no pertenece a este proyecto'],
            ]);
        }

        $this->projectAssignmentRepository->delete($assignment);
    }

    public function getProjectAssignments(string $projectUid)
    {
        $project = Project::query()->where('uid', $projectUid)->firstOrFail();

        return $this->projectAssignmentRepository->forProject($project->getKey());
    }

    public function getProjectTeam(string $projectUid)
    {
        return $this->getProjectAssignments($projectUid);
    }

    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'user_uid' => 'required|uuid',
            'role' => 'required|string|in:' . implode(',', array_keys(self::ROLES)),
            'hours_allocated' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['hours_allocated'] = $validated['hours_allocated'] ?? 0;

        return $validated;
    }

    private function resolveUser(string $uid): User
    {
        $user = User::query()->where('uid', $uid)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user_uid' => ['El usuario no existe o no pertenece a este tenant'],
            ]);
        }

        return $user;
    }

    private function stableUid(string $key): string
    {
        $hash = md5('project-resource-role:' . $key);

        return substr($hash, 0, 8)
            . '-' . substr($hash, 8, 4)
            . '-' . substr($hash, 12, 4)
            . '-' . substr($hash, 16, 4)
            . '-' . substr($hash, 20, 12);
    }
}
