<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_events', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('created_at');
            $table->index(['subject_user_id', 'purpose', 'revoked_at']);
        });

        Schema::dropIfExists('consent_records');
    }

    public function down(): void
    {
        Schema::table('consent_events', function (Blueprint $table): void {
            $table->dropIndex(['subject_user_id', 'purpose', 'revoked_at']);
            $table->dropColumn('revoked_at');
        });

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 80);
            $table->string('policy_version', 50);
            $table->boolean('accepted');
            $table->text('evidence')->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['case_id', 'purpose', 'decided_at']);
        });
    }
};
