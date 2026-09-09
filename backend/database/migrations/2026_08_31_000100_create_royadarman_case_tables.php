<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('public_reference', 24)->unique();
            $table->foreignId('patient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_type', 40);
            $table->string('status', 40)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->text('patient_name')->nullable();
            $table->text('patient_mobile');
            $table->char('patient_mobile_hash', 64)->index();
            $table->string('tehran_area', 80)->nullable();
            $table->string('preferred_contact_time', 40)->nullable();
            $table->text('contact_reason')->nullable();
            $table->string('budget_band', 30);
            $table->foreignId('current_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'submitted_at']);
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

        Schema::create('case_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 50);
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['assignee_user_id', 'purpose', 'released_at']);
        });

        Schema::create('case_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->ulid('correlation_id');
            $table->timestamp('created_at');
            $table->index(['case_id', 'created_at']);
        });

        Schema::create('clinical_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('original_name');
            $table->string('storage_disk', 50);
            $table->string('storage_key', 255)->unique();
            $table->string('detected_mime', 100)->nullable();
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64)->index();
            $table->string('status', 40)->index();
            $table->string('scan_provider', 80)->nullable();
            $table->text('scan_reference')->nullable();
            $table->text('scan_result')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_access_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('clinical_documents')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->string('purpose', 80);
            $table->string('action', 40);
            $table->string('result', 20);
            $table->text('context')->nullable();
            $table->timestamp('created_at');
            $table->index(['document_id', 'created_at']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('resource_type', 160);
            $table->string('resource_id', 64)->nullable();
            $table->string('result', 20);
            $table->text('reason')->nullable();
            $table->text('context')->nullable();
            $table->ulid('correlation_id');
            $table->timestamp('created_at');
            $table->index(['resource_type', 'resource_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('document_access_events');
        Schema::dropIfExists('clinical_documents');
        Schema::dropIfExists('case_status_events');
        Schema::dropIfExists('case_assignments');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('patient_cases');
    }
};

