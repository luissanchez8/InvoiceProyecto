<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /** Cualquiera autenticado puede listar */
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    /** Cualquiera autenticado puede ver un usuario */
    public function view(User $user, User $model): bool
    {
        return $user !== null;
    }

    /** Solo el rol "asistencia" puede crear (mostrar botón + permitir POST) */
    public function create(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('asistencia');
    }

    /** Mantengo como antes: dueño puede actualizar */
    public function update(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /** Mantengo como antes: dueño puede borrar */
    public function delete(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /** Invitar usuarios: dueño como antes (si quieres que sea asistencia, me dices) */
    public function invite(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function deleteMultiple(User $user): bool
    {
        return $user->isOwner();
    }
}
