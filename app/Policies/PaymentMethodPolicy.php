<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentMethodPolicy
{
    use HandlesAuthorization;

    /** Todos los autenticados pueden listar */
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    /** Ver un método concreto: autenticado y de la misma empresa */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user !== null && $user->hasCompany($paymentMethod->company_id);
    }

    /** Crear: solo rol asistencia */
    public function create(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('asistencia');
    }

    /** Actualizar: solo asistencia y misma empresa */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($paymentMethod->company_id);
    }

    /** Borrar: solo asistencia y misma empresa */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($paymentMethod->company_id);
    }

    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($paymentMethod->company_id);
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole('asistencia') && $user->hasCompany($paymentMethod->company_id);
    }
}
