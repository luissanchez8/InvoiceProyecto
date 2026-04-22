<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla failed_jobs si aún no existe.
 *
 * Laravel la usa para guardar los Jobs que fallaron tras agotar todos los
 * reintentos. La tabla `jobs` ya existe en la BD onf_app_* pero la de
 * failed_jobs aparentemente no se creó en migraciones anteriores.
 *
 * Sin esta tabla, un Job que agote sus tries intentará insertar en una
 * tabla inexistente y el worker se romperá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
