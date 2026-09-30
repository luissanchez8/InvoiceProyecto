<?php

namespace App\Models\Concerns;

use App\Models\Invoice;
use App\Services\Facturacion\Documentos;
use App\Services\Facturacion\EstadoFacturacion;

/**
 * Onfactu v.1.17.0 — Lo común a presupuestos, proformas y albaranes.
 *
 *   - Número al crearlos, también en borrador. No son documentos fiscales:
 *     un hueco en la numeración no importa, y un documento sin número no se
 *     puede nombrar al cliente ni enlazar a su factura.
 *   - Siempre con la plantilla única del PDF (Invoice::PLANTILLA_PDF).
 *   - Su estado de facturación (EstadoFacturacion).
 */
trait DocumentoComercial
{
    public static function bootDocumentoComercial(): void
    {
        static::saving(function ($doc) {
            if ($doc->template_name !== Invoice::PLANTILLA_PDF) {
                $doc->template_name = Invoice::PLANTILLA_PDF;
            }
        });

        static::created(function ($doc) {
            if (empty($doc->{Documentos::cfg($doc)['numero']}) && $doc->company_id) {
                $doc->assignNumber();
            }
        });
    }

    public function estaFacturado(): bool
    {
        return $this->billing_status === EstadoFacturacion::FACTURADO;
    }

    /** Facturas, anticipos y documentos relacionados, para la pantalla del documento. */
    public function getFacturacionAttribute(): array
    {
        return EstadoFacturacion::detalle($this);
    }
}
