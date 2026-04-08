<?php

namespace App\Repositories;

use App\Models\Contact;

class ContactRepository
{
    public function all()
    {
        return Contact::with('account')->get();
    }

    public function findByUid(string $uid)
    {
        return Contact::with('account')->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data)
    {
        if ($this->existsByEmail($data)) {
            throw new \Exception('El contacto ya existe en esta empresa');
        }

        return Contact::create($data);
    }

    public function update(string $uid, array $data)
    {
        $contact = $this->findByUid($uid);

        if ($this->existsByEmail($data, $contact->id)) {
            throw new \Exception('El contacto ya existe en esta empresa');
        }

        $contact->update($data);

        return $contact->fresh('account');
    }

    public function delete(string $uid)
    {
        $contact = $this->findByUid($uid);
        return $contact->delete();
    }

    private function existsByEmail(array $data, ?int $ignoreId = null): bool
    {
        if (empty($data['email'])) {
            return false;
        }

        return Contact::where('tenant_id', auth()->user()->tenant_id)
            ->where('email', $data['email'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
