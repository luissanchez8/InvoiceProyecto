<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('verifactu_status')->nullable()->after('status');
            $table->text('verifactu_qr')->nullable()->after('verifactu_status');
            $table->text('verifactu_signature')->nullable()->after('verifactu_qr');
            $table->timestamp('verifactu_signed_at')->nullable()->after('verifactu_signature');
            $table->text('verifactu_error')->nullable()->after('verifactu_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'verifactu_status',
                'verifactu_qr',
                'verifactu_signature',
                'verifactu_signed_at',
                'verifactu_error',
            ]);
        });
    }
};
