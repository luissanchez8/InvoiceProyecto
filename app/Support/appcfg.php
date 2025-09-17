<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lee un valor de la tabla app_config.
 * - No consulta si la tabla aún no existe (primera subida / migraciones).
 * - No rompe aunque no haya conexión o falle algo: devuelve $default.
 * - Memo por request para evitar múltiples queries.
 */
if (! function_exists('app_cfg')) {
    function app_cfg(string $key, $default = null) {
        static $memo, $ready;

        if ($ready === null) {
            try {
                // Si estamos en consola (migrate, config:cache, etc.) y aún no existe la tabla, no leemos
                $tableExists = Schema::hasTable('app_config');
                $ready = $tableExists;
            } catch (\Throwable $e) {
                // Si no podemos ni preguntar (p.ej. DB aún no conectada), considera no listo
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
                // Cualquier error aquí → no rompemos el render
                $memo = [];
            }
        }

        return $memo[$key] ?? $default;
    }
}
