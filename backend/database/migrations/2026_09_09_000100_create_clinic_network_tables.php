<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clinic branches (multiple locations per clinic)
        Schema::create('clinic_branches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 80);
            $table->string('district', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable()->index();
            $table->decimal('longitude', 11, 8)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('accepts_home_visits')->default(false);
            $table->boolean('accepts_emergency')->default(false);
            $table->timestamps();
            $table->index(['clinic_id', 'city', 'district']);
            $table->index(['latitude', 'longitude']);
        });

        // Clinic users (staff who work at clinics)
        Schema::create('clinic_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignUlid('clinic_branch_id')->nullable()->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->index(); // owner, manager, receptionist, dentist, hygienist
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'user_id']);
        });

        // Dentist profiles
        Schema::create('dentists', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_id', 20)->nullable();
            $table->char('national_id_hash', 64)->nullable()->unique();
            $table->string('medical_license_number', 50)->unique();
            $table->char('license_hash', 64)->unique();
            $table->string('specialty', 50)->nullable()->index();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->timestamps();
        });

        // Credentials (licenses, certifications)
        Schema::create('credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('dentist_id')->constrained('dentists')->cascadeOnDelete();
            $table->string('type', 50)->index(); // license, certification, degree
            $table->string('issuer', 100);
            $table->string('number', 100);
            $table->char('number_hash', 64)->unique();
            $table->date('issued_at');
            $table->date('expires_at')->nullable()->index();
            $table->string('status', 20)->default('active')->index(); // active, expired, suspended, revoked
            $table->string('verification_status', 20)->default('pending')->index(); // pending, verified, rejected
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Payout accounts for clinics
        Schema::create('payout_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('account_holder_name');
            $table->string('bank_name');
            $table->string('account_number', 50);
            $table->char('account_number_hash', 64)->unique();
            $table->string('sheba_code', 50)->nullable();
            $table->char('sheba_hash', 64)->nullable()->unique();
            $table->string('currency', 3)->default('IRR');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Services offered by clinics
        Schema::create('services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique()->index();
            $table->string('name_en');
            $table->string('name_fa');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_fa')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('category', 50)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Services offered at specific branches
        Schema::create('branch_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedBigInteger('base_price')->nullable(); // in smallest currency unit
            $table->unsignedBigInteger('min_price')->nullable();
            $table->unsignedBigInteger('max_price')->nullable();
            $table->string('price_currency', 3)->default('IRR');
            $table->boolean('is_available')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'service_id']);
        });

        // Operating hours for clinic branches
        Schema::create('operating_hours', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->string('day_of_week', 10)->index(); // monday, tuesday, etc.
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_24h')->default(false);
            $table->integer('break_start_hour')->nullable();
            $table->integer('break_end_hour')->nullable();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'day_of_week']);
        });

        // Holidays and exceptions
        Schema::create('holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->nullable()->constrained('clinic_branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->index(); // national, clinic, custom
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('all_day')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['clinic_branch_id', 'date']);
        });

        // Booking modes configuration
        Schema::create('booking_modes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->string('mode', 20)->index(); // instant, manual, both
            $table->boolean('enabled')->default(true);
            $table->integer('max_instant_slots')->default(10);
            $table->integer('manual_acceptance_sla_minutes')->default(5);
            $table->integer('hold_expiry_minutes')->default(10);
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_modes');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('branch_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('dentists');
        Schema::dropIfExists('clinic_users');
        Schema::dropIfExists('clinic_branches');
    }
};
