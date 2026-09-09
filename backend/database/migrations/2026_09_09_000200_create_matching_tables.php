<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referral requests (patient intake for matching)
        Schema::create('referral_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('patient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('patient_case_id')->nullable()->constrained('patient_cases')->nullOnDelete();
            $table->string('public_reference', 24)->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->string('service_type', 40)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('patient_name')->nullable();
            $table->text('patient_mobile');
            $table->char('patient_mobile_hash', 64)->index();
            $table->decimal('latitude', 10, 8)->nullable()->index();
            $table->decimal('longitude', 11, 8)->nullable()->index();
            $table->string('tehran_area', 80)->nullable();
            $table->string('preferred_contact_time', 40)->nullable();
            $table->text('contact_reason')->nullable();
            $table->string('budget_band', 30);
            $table->unsignedBigInteger('budget_amount')->nullable();
            $table->string('budget_currency', 3)->default('IRR');
            $table->string('preferred_gender', 10)->nullable();
            $table->string('preferred_language', 10)->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'submitted_at']);
            $table->index(['latitude', 'longitude']);
        });

        // Intake answers (patient responses to questions)
        Schema::create('intake_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_request_id')->constrained('referral_requests')->cascadeOnDelete();
            $table->string('question_key', 100)->index();
            $table->string('question_type', 20);
            $table->text('question_text')->nullable();
            $table->json('answer_value')->nullable();
            $table->string('answer_text')->nullable();
            $table->string('locale', 5);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_red_flag')->default(false);
            $table->timestamps();
            $table->unique(['referral_request_id', 'question_key']);
        });

        // Urgency assessments
        Schema::create('urgency_assessments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_request_id')->constrained('referral_requests')->cascadeOnDelete();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('level', 20)->index(); // low, medium, high, emergency
            $table->integer('score')->default(0);
            $table->json('criteria')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assessed_at');
            $table->timestamps();
        });

        // Patient preferences for matching
        Schema::create('patient_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_request_id')->constrained('referral_requests')->cascadeOnDelete();
            $table->string('preference_type', 50)->index();
            $table->string('preference_value');
            $table->string('weight', 10)->default('normal'); // low, normal, high
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        // Match runs (each matching attempt)
        Schema::create('match_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_request_id')->constrained('referral_requests')->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('strategy', 50)->index(); // proximity, availability, fairness, hybrid
            $table->json('criteria')->nullable();
            $table->integer('candidate_count')->default(0);
            $table->integer('result_count')->default(0);
            $table->string('status', 20)->default('running')->index();
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Match candidates (potential matches for a request)
        Schema::create('match_candidates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('match_run_id')->constrained('match_runs')->cascadeOnDelete();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->restrictOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->integer('travel_time_minutes')->nullable();
            $table->integer('score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->integer('rank')->default(0);
            $table->string('status', 20)->default('candidate')->index(); // candidate, selected, rejected, expired
            $table->boolean('is_available')->default(false);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['match_run_id', 'rank']);
            $table->index(['clinic_branch_id', 'status']);
        });

        // Match decisions (patient's choice)
        Schema::create('match_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('referral_request_id')->constrained('referral_requests')->cascadeOnDelete();
            $table->foreignUlid('match_candidate_id')->constrained('match_candidates')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20)->index(); // accepted, rejected, skipped
            $table->string('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['referral_request_id', 'match_candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_decisions');
        Schema::dropIfExists('match_candidates');
        Schema::dropIfExists('match_runs');
        Schema::dropIfExists('patient_preferences');
        Schema::dropIfExists('urgency_assessments');
        Schema::dropIfExists('intake_answers');
        Schema::dropIfExists('referral_requests');
    }
};
