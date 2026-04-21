<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando se intenta asignar un número de documento
 * (invoice_number, estimate_number, etc.) que ya está ocupado por otro
 * documento en la misma empresa.
 *
 * El array $details contiene información útil para el frontend:
 *  - conflicting_id: ID del documento que tiene el número
 *  - conflicting_number: el número ocupado
 *  - conflicting_status: estado del documento conflictivo (DRAFT, APPROVED, etc.)
 *  - attempted_number: el número que se intentaba asignar
 *
 * Así el frontend puede construir un mensaje del tipo "La factura #42
 * (BORRADOR) tiene el número INV-000003. Haz click aquí para editarla."
 */
class NumberCollisionException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    protected array $details;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
