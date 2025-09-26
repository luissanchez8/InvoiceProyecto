<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Ajusta el "after" si tu motor lo soporta; no es obligatorio.
            if (!Schema::hasColumn('companies', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('contact_email');
            }
            if (!Schema::hasColumn('companies', 'website')) {
                $table->string('website')->nullable()->after('contact_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'contact_email')) {
                $table->dropColumn('contact_email');
            }
            if (Schema::hasColumn('companies', 'contact_name')) {
                $table->dropColumn('contact_name');
            }
            if (Schema::hasColumn('companies', 'website')) {
                $table->dropColumn('website');
            }
        });
    }
};
