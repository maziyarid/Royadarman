<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_grants', function (Blueprint $table): void {
            $table->foreignUlid('consent_event_id')->nullable()->after('proposal_id')->constrained('consent_events')->restrictOnDelete();
            $table->index('consent_event_id');
        });

        Schema::table('clinical_documents', function (Blueprint $table): void {
            $table->foreignUlid('consent_event_id')->nullable()->after('uploaded_by_user_id')->constrained('consent_events')->restrictOnDelete();
            $table->index(['case_id', 'consent_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('clinical_documents', function (Blueprint $table): void {
            $table->dropIndex(['case_id', 'consent_event_id']);
            $table->dropColumn('consent_event_id');
        });

        Schema::table('referral_grants', function (Blueprint $table): void {
            $table->dropIndex('consent_event_id');
            $table->dropColumn('consent_event_id');
        });
    }
};
