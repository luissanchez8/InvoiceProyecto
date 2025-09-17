<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * app_cfg('KEY', $default)
 * Nunca lanza excepciones ni warnings al renderizar vistas:
 * - No consulta si no hay conexión o no existe la tabla.
 * - Devuelve $default ante cualquier fallo.
 * - Memo por request para evitar múltiples queries.
 */
if (! function_exists('app_cfg')) {
    function app_cfg(string $key, $default = null) {
        static $memo = null;
        static $ready = null;

        if ($ready === null) {
            try {
                // Asegura que hay conexión y que la tabla existe
                // Nota: hasTable internamente abre conexión; si falla, capturamos
                $ready = Schema::hasTable('app_config');
            } catch (\Throwable $e) {
                $ready = false;
            }
        }

        if (!$ready) {
            return $default;
        }

        if ($memo === null) {
            try {
                $memo = DB::table('app_config')->pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                $memo = []; // nunca re-lanzar
            }
        }

        return $memo[$key] ?? $default;
    }
}
