<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('role', 32)->default('patient')->index();
            $table->string('locale', 5)->default('fa');
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->unique();
            $table->text('totp_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_authenticated_at')->nullable();
        });

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->text('phone');
            $table->char('phone_hash', 64)->index();
            $table->string('code_hash');
            $table->string('locale', 5);
            $table->string('purpose', 24)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_sent_at');
            $table->timestamp('used_at')->nullable();
            $table->char('request_ip_hash', 64);
            $table->timestamps();
            $table->index(['phone_hash', 'used_at', 'expires_at']);
        });

        Schema::create('policy_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('policy_key', 80);
            $table->string('version', 50);
            $table->string('locale', 5);
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_key', 'version', 'locale']);
            $table->index(['policy_key', 'locale', 'published_at']);
        });

        Schema::create('consent_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('case_id')->nullable()->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignUlid('policy_version_id')->constrained('policy_versions')->restrictOnDelete();
            $table->string('purpose', 80);
            $table->string('decision', 20);
            $table->string('locale', 5);
            $table->string('channel', 20);
            $table->char('ip_hash', 64);
            $table->char('user_agent_hash', 64);
            $table->timestamp('created_at');
            $table->index(['subject_user_id', 'purpose', 'created_at']);
        });

        Schema::create('clinics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('city', 80);
            $table->string('area_code', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('practitioners', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->text('licence_number');
            $table->char('licence_hash', 64)->unique();
            $table->string('credential_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('clinic_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('membership_role', 32);
            $table->timestamp('active_from');
            $table->timestamp('active_until')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'user_id']);
        });

        Schema::table('patient_cases', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->string('source_language', 5)->default('fa');
            $table->string('currency', 3)->default('IRR');
            $table->string('budget_input_unit', 8)->default('toman');
        });

        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('operation', 100);
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['actor_user_id', 'operation', 'idempotency_key'], 'idempotency_actor_operation_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('clinic_memberships');
        Schema::dropIfExists('practitioners');
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('consent_events');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('otp_challenges');
        Schema::table('patient_cases', fn (Blueprint $table) => $table->dropColumn(['version', 'source_language', 'currency', 'budget_input_unit']));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone_hash']);
            $table->dropColumn(['role', 'locale', 'phone', 'phone_hash', 'totp_secret', 'mfa_recovery_codes', 'is_active', 'last_authenticated_at']);
        });
    }
};
