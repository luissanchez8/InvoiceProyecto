<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    /**
     * Regla común de autorización
     */
    protected function canManage(User $user, Company $company): bool
    {
        // Owner de la empresa
        if ($user->id === $company->owner_id) {
            return true;
        }

        // Rol asistencia
        if ($user->role === 'asistencia') {
            return true;
        }

        // Miembro de la empresa (pivot user_company)
        return $user->companies()
            ->where('companies.id', $company->id)
            ->exists();
    }

    public function view(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->id === $company->owner_id;
    }

    public function transferOwnership(User $user, Company $company): bool
    {
        return $user->id === $company->owner_id;
    }
}
