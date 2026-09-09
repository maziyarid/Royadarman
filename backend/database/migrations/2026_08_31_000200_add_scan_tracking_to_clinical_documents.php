<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_documents', function (Blueprint $table): void {
            $table->timestamp('scan_attempted_at')->nullable()->after('scan_result');
            $table->timestamp('scan_completed_at')->nullable()->after('scan_attempted_at');
            $table->string('scan_error_code', 120)->nullable()->after('scan_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_documents', function (Blueprint $table): void {
            $table->dropColumn(['scan_attempted_at', 'scan_completed_at', 'scan_error_code']);
        });
    }
};

