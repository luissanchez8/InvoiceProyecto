<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\Database\Role;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Listar roles (index).
     * Todos pueden listar. El filtrado del rol "asistencia" se hace en view() o en la query del controlador.
     */
    public function viewAny(User $user): bool
    {
        return $user !== null; // cualquier usuario autenticado
    }

    /**
     * Ver un rol concreto.
     * - El usuario con rol "asistencia" puede verlo todo.
     * - Para el resto, se deniega si el rol consultado es "asistencia".
     */
    public function view(User $user, Role $role): bool
    {
        if ($user->hasRole('asistencia')) {
            return true;
        }

        return strtolower($role->name) !== 'asistencia';
    }

    /**
     * Crear roles: solo el usuario "asistencia".
     */
    public function create(User $user): bool
    {
        return $user->hasRole('asistencia');
    }

    /**
     * Actualizar roles: solo "asistencia".
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('asistencia');
    }

    /**
     * Borrar roles: solo "asistencia".
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('asistencia');
    }

    /**
     * Restaurar: solo "asistencia".
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasRole('asistencia');
    }

    /**
     * Borrado permanente: solo "asistencia".
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasRole('asistencia');
    }
}
