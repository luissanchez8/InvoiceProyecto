<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AddressPolicy
{
    use HandlesAuthorization;

    protected function canManage(User $user, Address $address): bool
    {
        $company = $address->company;
        if (!$company) return false;

        if ($user->id === $company->owner_id) return true;
        if ($user->role === 'asistencia') return true;

        // miembro de la empresa
        return $user->companies()
            ->where('companies.id', $company->id)
            ->exists();
    }

    public function view(User $user, Address $address): bool
    {
        return $this->canManage($user, $address);
    }

    public function update(User $user, Address $address): bool
    {
        return $this->canManage($user, $address);
    }
}
