<?php

namespace app\Helpers;

class AppConfig
{
    private static $config = null;

    public static function load()
    {
        if (self::$config === null) {
            $configFile = base_path('appConfig.cfg');
            self::$config = [];

            if (file_exists($configFile)) {
                foreach (file($configFile) as $line) {
                    $line = trim($line);
                    if ($line && strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $value = trim($value, " \t\n\r\0\x0B\";");
                        self::$config[trim($key)] = $value;
                    }
                }
            }

            return self::$config;
        }

        return self::$config;
    }

    public static function get($key, $default = null)
    {
        $config = self::load();

        return $config[$key] ?? $default;
    }
}
