<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu: garantiza que URL_LOGOTIPO apunte al asset local
 * (/images/logo-onfactu-margen.png) en lugar de a la URL externa
 * https://onfactu.com/... que tardaba en cargar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $localUrl = '/images/logo-onfactu-margen.png';

        $existe = DB::table('app_config')->where('key', 'URL_LOGOTIPO')->exists();

        if ($existe) {
            DB::table('app_config')
                ->where('key', 'URL_LOGOTIPO')
                ->where('value', 'like', 'http%onfactu.com%')
                ->update([
                    'value' => $localUrl,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('app_config')->insert([
                'key' => 'URL_LOGOTIPO',
                'value' => $localUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
