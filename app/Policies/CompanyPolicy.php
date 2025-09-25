<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    // (opcional) asistencia / super admin siempre puede ver/editar
    public function before(User $user, $ability)
    {
        if (in_array($user->role, ['asistencia', 'super admin']) && in_array($ability, ['view','update'])) {
            return true;
        }
    }

    public function view(User $user, Company $company): bool
    {
        return $this->isOwnerOrMember($user, $company);
    }

    public function update(User $user, Company $company): bool
    {
        // si quieres que solo owner edite, usa === en vez de helper:
        return $this->isOwnerOrMember($user, $company);
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

    private function isOwnerOrMember(User $user, Company $company): bool
    {
        if ($user->id === $company->owner_id) return true;

        // Si tienes relación $company->users()
        if (method_exists($company, 'users')) {
            return $company->users()->whereKey($user->id)->exists();
        }

        // Fallback directo al pivot 'user_company'
        return \DB::table('user_company')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->exists();
    }
}
