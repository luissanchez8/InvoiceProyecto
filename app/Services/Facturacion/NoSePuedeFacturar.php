<?php

namespace App\Services\Facturacion;

use RuntimeException;

/**
 * Onfactu v.1.17.0 — Motivo por el que no se puede facturar un documento.
 * El mensaje es el que ve el usuario: corto y sin tecnicismos.
 */
class NoSePuedeFacturar extends RuntimeException
{
    public function respuesta()
    {
        return response()->json([
            'error' => 'cannot_invoice',
            'message' => $this->getMessage(),
            'errors' => ['facturacion' => [$this->getMessage()]],
        ], 422);
    }
}
