<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * Onfactu — Ordenar las listas solo por campos permitidos.
 *
 * Antes, cada modelo hacía orderBy() con el campo que mandaba el navegador,
 * tal cual. Dos problemas:
 *  - las columnas calculadas de las listas (el nombre del cliente en
 *    facturas, la forma de pago en cobros…) no existen en la tabla, y la
 *    consulta fallaba con "Se ha producido un error";
 *  - un campo que llega del navegador no debe ir sin validar a la consulta.
 *
 * Ahora se acepta:
 *  - una columna real de la tabla (se comprueba contra su estructura);
 *  - un campo calculado que el modelo declare en ordenesExtra(), que se
 *    ordena con una subconsulta y funciona haya o no uniones en la consulta.
 * Cualquier otra cosa se ignora y se ordena por lo más reciente.
 */
trait OrdenSeguro
{
    /** Columnas de cada tabla, consultadas una vez por petición. */
    private static array $columnasOrdenables = [];

    /**
     * Campos calculados que se pueden ordenar: campo => subconsulta SQL.
     * Cada modelo define los suyos.
     */
    protected function ordenesExtra(): array
    {
        return [];
    }

    public function scopeOrdenSeguro($query, $campo, $sentido = 'desc')
    {
        $sentido = strtolower((string) $sentido) === 'asc' ? 'asc' : 'desc';
        $campo = (string) $campo;
        $tabla = $this->getTable();

        $extra = $this->ordenesExtra();
        if ($campo !== '' && isset($extra[$campo])) {
            return $query->orderByRaw('('.$extra[$campo].') '.$sentido);
        }

        self::$columnasOrdenables[$tabla] ??= Schema::getColumnListing($tabla);
        if ($campo !== '' && in_array($campo, self::$columnasOrdenables[$tabla], true)) {
            return $query->orderBy($tabla.'.'.$campo, $sentido);
        }

        // Campo desconocido: orden por defecto en vez de un error
        return $query->orderBy($tabla.'.id', 'desc');
    }
}
