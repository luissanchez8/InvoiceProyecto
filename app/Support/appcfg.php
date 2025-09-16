<?php

use App\Models\AppConfig;

/**
 * Lee un valor de la tabla app_config.
 * Sin caché para que el cambio sea inmediato al tocar la BD.
 * Si quieres microcaché, puedes añadir Cache::remember con TTL 3–5s.
 */
if (! function_exists('app_cfg')) {
    function app_cfg(string $key, $default = null) {
        static $memo = null; // memo por-request (no persiste entre peticiones)
        if ($memo === null) {
            $memo = AppConfig::query()->pluck('value','key')->toArray();
        }
        return $memo[$key] ?? $default;
    }
}