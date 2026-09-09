<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support cases (customer support tickets)
        Schema::create('support_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('case_number', 24)->unique();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->index(); // complaint, inquiry, technical, billing
            $table->string('priority', 20)->default('normal')->index(); // low, normal, high, urgent
            $table->string('status', 20)->default('open')->index(); // open, in_progress, resolved, closed, escalated
            $table->string('subject');
            $table->text('description');
            $table->string('channel', 20)->default('web'); // web, phone, email, sms
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUlid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['requester_user_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        // Support case messages
        Schema::create('support_case_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->string('sender_type', 20); // patient, staff, system
            $table->boolean('is_internal')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Complaints
        Schema::create('complaints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('complainant_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('complaint_number', 24)->unique();
            $table->string('type', 50)->index(); // service, behavior, billing, safety
            $table->string('severity', 20)->default('medium')->index(); // low, medium, high, critical
            $table->string('status', 20)->default('received')->index(); // received, investigating, resolved, dismissed, escalated
            $table->string('subject');
            $table->text('description');
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUlid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignUlid('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // Reviews (patient feedback)
        Schema::create('reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->integer('rating')->nullable(); // 1-5
            $table->text('feedback')->nullable();
            $table->string('recommendation', 20)->nullable(); // yes, no, maybe
            $table->json('ratings_breakdown')->nullable(); // individual category ratings
            $table->string('status', 20)->default('draft')->index(); // draft, submitted, published, hidden
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'patient_user_id']);
        });

        // Notifications (system-generated)
        Schema::create('notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 100)->index();
            $table->string('title');
            $table->text('message');
            $table->string('severity', 20)->default('info')->index(); // info, warning, error, success
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->string('channel', 20)->default('in_app'); // in_app, email, sms, push
            $table->string('status', 20)->default('pending')->index(); // pending, sent, delivered, read, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_user_id', 'status']);
            $table->index(['recipient_user_id', 'created_at']);
        });

        // Audit events (already exists, but let's enhance it)
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->string('module', 50)->nullable()->after('action');
            $table->string('entity_type', 100)->nullable()->after('resource_type');
            $table->string('entity_id', 64)->nullable()->after('resource_id');
        });

        // Fraud signals (suspicious activity detection)
        Schema::create('fraud_signals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->index(); // rate_limit, duplicate, suspicious_pattern
            $table->string('severity', 20)->default('low')->index(); // low, medium, high, critical
            $table->string('status', 20)->default('detected')->index(); // detected, reviewed, confirmed, dismissed
            $table->text('description');
            $table->json('context')->nullable();
            $table->string('trigger', 50)->nullable(); // ip, behavior, pattern
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['type', 'triggered_at']);
        });

        // Clinic credentialing (verification process)
        Schema::create('clinic_credentialing', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, in_review, approved, rejected, suspended
            $table->string('stage', 50)->default('application'); // application, documents, verification, approval
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('requirements')->nullable(); // checklist of required documents
            $table->json('submitted_documents')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Document verification requests
        Schema::create('document_verifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 50)->index(); // license, id, certificate
            $table->string('status', 20)->default('pending')->index(); // pending, verified, rejected
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropColumn(['module', 'entity_type', 'entity_id']);
        });

        Schema::dropIfExists('document_verifications');
        Schema::dropIfExists('clinic_credentialing');
        Schema::dropIfExists('fraud_signals');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('support_case_messages');
        Schema::dropIfExists('support_cases');
    }
};
