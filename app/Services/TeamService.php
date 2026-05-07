<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function list()
    {
        return Team::query()->with(['manager', 'members'])->orderBy('name')->get();
    }

    public function get(string $uid): Team
    {
        return Team::query()->with(['manager', 'members'])->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): Team
    {
        $validated = $this->validate($data);

        return DB::transaction(function () use ($validated) {
            $memberUids = $validated['member_uids'] ?? [];
            unset($validated['member_uids']);

            $validated['manager_user_id'] = $this->resolveUserId($validated['manager_uid'] ?? $validated['leader_uid'] ?? null);
            unset($validated['manager_uid'], $validated['leader_uid'], $validated['leader_name']);

            $team = Team::query()->create($validated);
            $this->syncMembers($team, $memberUids);

            return $team->fresh(['manager', 'members']);
        });
    }

    public function update(string $uid, array $data): Team
    {
        $team = $this->get($uid);
        $validated = $this->validate($data, true);

        return DB::transaction(function () use ($team, $validated) {
            $memberUids = $validated['member_uids'] ?? null;
            unset($validated['member_uids']);

            if (array_key_exists('manager_uid', $validated) || array_key_exists('leader_uid', $validated)) {
                $validated['manager_user_id'] = $this->resolveUserId($validated['manager_uid'] ?? $validated['leader_uid']);
            }
            unset($validated['manager_uid'], $validated['leader_uid'], $validated['leader_name']);

            $team->update($validated);

            if ($memberUids !== null) {
                $this->syncMembers($team, $memberUids);
            }

            return $team->fresh(['manager', 'members']);
        });
    }

    public function delete(string $uid): void
    {
        $team = $this->get($uid);

        if ($team->members()->exists()) {
            throw ValidationException::withMessages([
                'team' => ['No puedes eliminar un equipo con miembros asignados'],
            ]);
        }

        $team->delete();
    }

    public function addMember(string $uid, array $data): Team
    {
        $validated = Validator::make($data, [
            'user_uid' => 'required|uuid',
        ])->validate();

        $team = $this->get($uid);
        $team->members()->syncWithoutDetaching([$this->resolveUserId($validated['user_uid'])]);

        return $team->fresh(['manager', 'members']);
    }

    public function removeMember(string $uid, string $userUid): void
    {
        $team = $this->get($uid);
        $team->members()->detach($this->resolveUserId($userUid));
    }

    private function validate(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'manager_uid' => 'nullable|uuid',
            'leader_uid' => 'nullable|uuid',
            'leader_name' => 'nullable|string|max:255',
            'member_uids' => 'sometimes|array',
            'member_uids.*' => 'uuid',
            'is_active' => 'sometimes|boolean',
        ])->validate();
    }

    private function syncMembers(Team $team, array $memberUids): void
    {
        $ids = collect($memberUids)->map(fn (string $uid) => $this->resolveUserId($uid))->filter()->all();
        $team->members()->sync($ids);
    }

    private function resolveUserId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $id = User::query()->where('uid', $uid)->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'user_uid' => ['El usuario no existe o no pertenece a este tenant'],
            ]);
        }

        return $id;
    }
}
