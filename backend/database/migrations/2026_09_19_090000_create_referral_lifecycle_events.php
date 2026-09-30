<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_lifecycle_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('proposal_id')->constrained('referral_proposals')->cascadeOnDelete();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['proposal_id', 'created_at']);
            $table->index(['case_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_lifecycle_events');
    }
};
