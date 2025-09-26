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
        // Cualquier usuario asociado a la empresa puede ver la sección (GET)
        $belongs = $user->companies()->where('company_id', $company->id)->exists();
    
        return $belongs || $user->id == $company->owner_id || $user->role === 'asistencia';
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
