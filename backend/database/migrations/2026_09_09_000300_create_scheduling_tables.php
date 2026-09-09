<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Availability rules (recurring patterns)
        Schema::create('availability_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->string('day_of_week', 10)->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->string('type', 20)->default('regular'); // regular, override, exception
            $table->date('effective_date')->nullable();
            $table->date('expires_date')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->integer('max_appointments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'dentist_id', 'day_of_week', 'start_time', 'end_time']);
        });

        // Capacity windows (time blocks for manual acceptance)
        Schema::create('capacity_windows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity')->default(1);
            $table->integer('booked_count')->default(0);
            $table->string('status', 20)->default('open')->index(); // open, full, closed
            $table->boolean('allow_overbooking')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['clinic_branch_id', 'date']);
            $table->index(['dentist_id', 'date']);
        });

        // Appointment slots (specific time slots for instant booking)
        Schema::create('appointment_slots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');
            $table->unsignedBigInteger('price')->nullable();
            $table->string('price_currency', 3)->default('IRR');
            $table->string('status', 20)->default('available')->index(); // available, booked, held, cancelled, expired
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['clinic_branch_id', 'date', 'start_time']);
            $table->index(['dentist_id', 'date']);
            $table->index(['status', 'date']);
        });

        // Slot holds (temporary reservations)
        Schema::create('slot_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_slot_id')->constrained('appointment_slots')->restrictOnDelete();
            $table->foreignUlid('referral_request_id')->nullable()->constrained('referral_requests')->nullOnDelete();
            $table->foreignId('patient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique()->index();
            $table->string('status', 20)->default('active')->index(); // active, expired, converted, cancelled
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at');
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->text('metadata')->nullable();
            $table->unique(['appointment_slot_id', 'status']);
        });

        // Appointments
        Schema::create('appointments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('public_reference', 24)->unique();
            $table->foreignUlid('clinic_branch_id')->constrained('clinic_branches')->restrictOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists')->nullOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('slot_hold_id')->nullable()->constrained('slot_holds')->nullOnDelete();
            $table->foreignUlid('appointment_slot_id')->nullable()->constrained('appointment_slots')->nullOnDelete();
            $table->foreignUlid('capacity_window_id')->nullable()->constrained('capacity_windows')->nullOnDelete();
            $table->foreignUlid('referral_request_id')->nullable()->constrained('referral_requests')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');
            $table->unsignedBigInteger('visit_fee')->nullable();
            $table->string('visit_fee_currency', 3)->default('IRR');
            $table->string('status', 40)->default('pending')->index(); // pending, confirmed, cancelled, completed, no_show
            $table->string('booking_mode', 20)->default('instant'); // instant, manual
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['clinic_branch_id', 'date']);
            $table->index(['patient_user_id', 'status']);
            $table->index(['dentist_id', 'date']);
        });

        // Appointment status history
        Schema::create('appointment_status_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->index(['appointment_id', 'changed_at']);
        });

        // Check-in records
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('expected_arrival_at')->nullable();
            $table->string('status', 20)->default('checked_in')->index(); // checked_in, waiting, in_progress, completed
            $table->string('method', 20)->nullable(); // online, in_person, phone
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Visit confirmations
        Schema::create('visit_confirmations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('outcome', 50)->nullable(); // completed, partial, cancelled, referred
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('requires_follow_up')->default(false);
            $table->timestamp('follow_up_due_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_confirmations');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('slot_holds');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('capacity_windows');
        Schema::dropIfExists('availability_rules');
    }
};
