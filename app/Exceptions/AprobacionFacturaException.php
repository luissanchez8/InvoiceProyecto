<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Onfactu v.1.13.0 — Motivo por el que una factura no se puede aprobar.
 *
 * Lleva un código para que la pantalla decida qué ofrecer (por ejemplo,
 * "usar la fecha de hoy") y un mensaje listo para enseñar al usuario.
 */
class AprobacionFacturaException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly array $datos = [],
    ) {
        parent::__construct($mensaje);
    }

    public function respuesta()
    {
        return response()->json([
            'success' => false,
            'code'    => $this->codigo,
            'message' => $this->getMessage(),
        ] + $this->datos, 422);
    }
}
