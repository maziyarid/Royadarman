<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('clinical_documents')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 24);
            $table->string('engine', 80)->nullable();
            $table->char('file_hash', 64);
            $table->string('error_code', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unique(['document_id', 'attempt_number']);
        });

        Schema::create('coordination_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('assignee_user_id')->constrained('users')->restrictOnDelete();
            $table->string('task_type', 50);
            $table->string('status', 24)->default('open');
            $table->text('operational_note')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_proposals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('proposed')->index();
            $table->text('reasoning');
            $table->string('source_language', 5);
            $table->timestamp('proposed_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('proposal_id')->unique()->constrained('referral_proposals')->cascadeOnDelete();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->json('scope');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('review_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('clinician_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('supersedes_id')->nullable()->constrained('review_revisions')->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('source_language', 5);
            $table->text('image_adequacy');
            $table->longText('observations');
            $table->longText('limitations');
            $table->longText('options');
            $table->longText('recommended_next_step');
            $table->string('budget_band', 30)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['case_id', 'revision_number']);
        });

        Schema::create('publication_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_revision_id')->constrained('review_revisions')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 24);
            $table->timestamp('created_at');
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 64);
            $table->string('recipient_locale', 5)->nullable();
            $table->json('payload');
            $table->string('deduplication_key', 160)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('provider_reference', 120)->nullable();
            $table->string('status', 24)->index();
            $table->string('recipient_locale', 5);
            $table->string('template_key', 100);
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['outbox_event_id', 'channel']);
        });

        Schema::create('retention_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('resource_type', 100);
            $table->string('resource_id', 64);
            $table->string('status', 24)->default('pending');
            $table->timestamp('execute_after')->index();
            $table->text('exception_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_jobs');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('publication_events');
        Schema::dropIfExists('review_revisions');
        Schema::dropIfExists('referral_grants');
        Schema::dropIfExists('referral_proposals');
        Schema::dropIfExists('coordination_tasks');
        Schema::dropIfExists('scan_attempts');
    }
};

