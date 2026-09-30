<?php

namespace App\Models;

use App\Services\Facturacion\Documentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onfactu v.1.17.0 — Qué factura ha salido de qué presupuesto, proforma o
 * albarán. tipo = 'final' (la factura del documento) o 'anticipo'.
 */
class DocumentoFacturado extends Model
{
    public const FINAL = 'final';

    public const ANTICIPO = 'anticipo';

    public const UPDATED_AT = null;

    protected $table = 'documentos_facturados';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function documento(): ?Model
    {
        return Documentos::buscar($this->documento_tipo, (int) $this->documento_id);
    }
}
