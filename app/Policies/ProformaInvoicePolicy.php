<?php

/**
 * Policy ProformaInvoicePolicy — Autorización de facturas proforma
 *
 * Define quién puede ver, crear, editar, eliminar y enviar facturas proforma.
 * Usa Bouncer (Silber) para verificar abilities por empresa.
 */

namespace App\Policies;

use App\Models\ProformaInvoice;
use App\Models\User;
use Silber\Bouncer\BouncerFacade;

class ProformaInvoicePolicy
{
    /** Listar facturas proforma */
    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-proforma-invoice');
    }

    /** Ver una factura proforma individual */
    public function view(User $user, ProformaInvoice $proformaInvoice): bool
    {
        return BouncerFacade::can('view-proforma-invoice')
            && $user->hasCompany($proformaInvoice->company_id);
    }

    /** Crear una nueva factura proforma */
    public function create(User $user): bool
    {
        return BouncerFacade::can('create-proforma-invoice');
    }

    /** Editar una factura proforma existente */
    public function update(User $user, ProformaInvoice $proformaInvoice): bool
    {
        return BouncerFacade::can('edit-proforma-invoice')
            && $user->hasCompany($proformaInvoice->company_id)
            && $proformaInvoice->allow_edit;
    }

    /** Eliminar una factura proforma */
    public function delete(User $user, ProformaInvoice $proformaInvoice): bool
    {
        return BouncerFacade::can('delete-proforma-invoice')
            && $user->hasCompany($proformaInvoice->company_id);
    }

    /** Eliminación masiva (usada en Gate) */
    public function deleteMultiple(User $user): bool
    {
        return BouncerFacade::can('delete-proforma-invoice');
    }

    /** Enviar por email */
    public function send(User $user, ProformaInvoice $proformaInvoice): bool
    {
        return BouncerFacade::can('send-proforma-invoice');
    }
}
