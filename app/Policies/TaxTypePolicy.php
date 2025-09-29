<?php

namespace App\Policies;

use App\Models\TaxType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
// use Silber\Bouncer\BouncerFacade as Bouncer; // opcional si quieres combinar con abilities

class TaxTypePolicy
{
    use HandlesAuthorization;

    /**
     * Listar tipos de impuesto.
     * Cualquier usuario autenticado puede listar (el filtrado por empresa hazlo en la query del controlador).
     */
    public function viewAny(User $user): bool
    {
        return $user !== null;
        // Si quisieras exigir ability además del login:
        // return $user !== null && Bouncer::can('view-tax-type', TaxType::class);
    }

    /**
     * Ver un tipo concreto: cualquiera autenticado, pero SOLO si pertenece a su empresa.
     */
    public function view(User $user, TaxType $taxType): bool
    {
        return $user !== null && $user->hasCompany($taxType->company_id);
        // O combinado con ability:
        // return $user !== null
        //     && Bouncer::can('view-tax-type', $taxType)
        //     && $user->hasCompany($taxType->company_id);
    }

    /**
     * Crear: SOLO rol "asistencia".
     */
    public function create(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('asistencia');
        // O combinado con ability:
        // return $user->hasRole('asistencia') && Bouncer::can('create-tax-type', TaxType::class);
    }

    /**
     * Actualizar: SOLO rol "asistencia" y dentro de su empresa.
     */
    public function update(User $user, TaxType $taxType): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($taxType->company_id);
        // O combinado con ability:
        // return $user->hasRole('asistencia')
        //     && Bouncer::can('edit-tax-type', $taxType)
        //     && $user->hasCompany($taxType->company_id);
    }

    /**
     * Borrar: SOLO rol "asistencia" y dentro de su empresa.
     */
    public function delete(User $user, TaxType $taxType): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($taxType->company_id);
        // O combinado con ability:
        // return $user->hasRole('asistencia')
        //     && Bouncer::can('delete-tax-type', $taxType)
        //     && $user->hasCompany($taxType->company_id);
    }

    public function restore(User $user, TaxType $taxType): bool
    {
        return $this->delete($user, $taxType);
    }

    public function forceDelete(User $user, TaxType $taxType): bool
    {
        return $this->delete($user, $taxType);
    }
}
