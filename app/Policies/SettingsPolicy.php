<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingsPolicy
{
    use HandlesAuthorization;

    public function manageCompany(User $user, Company $company)
    {
        // 1. Owner siempre puede
        if ($user->id === $company->owner_id) {
            return true;
        }

        // 2. Rol "asistencia" también puede
        if ($user->role === 'asistencia') {
            return true;
        }

        // 3. Si quieres, también permite a cualquier usuario ligado a esa empresa
        if ($user->companies()->where('companies.id', $company->id)->exists()) {
            return true;
        }

        // 4. Si no cumple nada → denegado
        return false;
    }

    public function manageBackups(User $user)
    {
        return $user->isOwner();
    }

    public function manageFileDisk(User $user)
    {
        return $user->isOwner();
    }

    public function manageEmailConfig(User $user)
    {
        return $user->isOwner();
    }

    public function managePDFConfig(User $user)
    {
        return $user->isOwner();
    }

    public function manageSettings(User $user)
    {
        return $user->isOwner();
    }
}
