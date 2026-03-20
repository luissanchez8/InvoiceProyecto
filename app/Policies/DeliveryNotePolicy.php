<?php

/**
 * Policy DeliveryNotePolicy — Autorización de albaranes
 *
 * Define quién puede ver, crear, editar, eliminar y enviar albaranes.
 * Usa Bouncer (Silber) para verificar abilities por empresa.
 */

namespace App\Policies;

use App\Models\DeliveryNote;
use App\Models\User;
use Silber\Bouncer\BouncerFacade;

class DeliveryNotePolicy
{
    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-delivery-note');
    }

    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        return BouncerFacade::can('view-delivery-note')
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-delivery-note');
    }

    public function update(User $user, DeliveryNote $deliveryNote): bool
    {
        return BouncerFacade::can('edit-delivery-note')
            && $user->hasCompany($deliveryNote->company_id)
            && $deliveryNote->allow_edit;
    }

    public function delete(User $user, DeliveryNote $deliveryNote): bool
    {
        return BouncerFacade::can('delete-delivery-note')
            && $user->hasCompany($deliveryNote->company_id);
    }

    public function deleteMultiple(User $user): bool
    {
        return BouncerFacade::can('delete-delivery-note');
    }

    public function send(User $user, DeliveryNote $deliveryNote): bool
    {
        return BouncerFacade::can('send-delivery-note');
    }
}
