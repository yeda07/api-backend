<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class TeamHierarchyService
{
    public function assignManager(string $userUid, ?string $managerUid): User
    {
        $user = $this->findUser($userUid);
        $manager = $managerUid ? $this->findUser($managerUid) : null;

        if ($manager && $manager->getKey() === $user->getKey()) {
            throw ValidationException::withMessages([
                'manager_uid' => ['Un usuario no puede ser su propio manager'],
            ]);
        }

        $user->manager_id = $manager?->getKey();
        $user->save();

        return $user->fresh('manager');
    }

    public function assignAccountOwner(string $accountUid, string $ownerUserUid): Account
    {
        $account = Account::query()->where('uid', $accountUid)->first();
        $owner = $this->findUser($ownerUserUid);

        if (!$account) {
            throw new ModelNotFoundException('Cuenta no encontrada');
        }

        $account->owner_user_id = $owner->getKey();
        $account->save();

        return $account->fresh('owner');
    }

    public function assignContactOwner(string $contactUid, string $ownerUserUid): Contact
    {
        $contact = Contact::query()->where('uid', $contactUid)->first();
        $owner = $this->findUser($ownerUserUid);

        if (!$contact) {
            throw new ModelNotFoundException('Contacto no encontrado');
        }

        $contact->owner_user_id = $owner->getKey();
        $contact->save();

        return $contact->fresh(['owner', 'account']);
    }

    public function assignCrmEntityOwner(string $crmEntityUid, string $ownerUserUid): CrmEntity
    {
        $crmEntity = CrmEntity::query()->where('uid', $crmEntityUid)->first();
        $owner = $this->findUser($ownerUserUid);

        if (!$crmEntity) {
            throw new ModelNotFoundException('Entidad CRM no encontrada');
        }

        $crmEntity->owner_user_id = $owner->getKey();
        $crmEntity->save();

        return $crmEntity->fresh('owner');
    }

    private function findUser(string $uid): User
    {
        $user = User::query()->where('uid', $uid)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user_uid' => ['El usuario no existe o no pertenece a este tenant'],
            ]);
        }

        return $user;
    }
}
