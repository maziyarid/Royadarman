<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_service_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('patient_cases')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tehran_area', 80)->index();
            $table->string('status', 24)->default('requested')->index();
            $table->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_acceptance_status', 24)->nullable();
            $table->timestamp('patient_confirmed_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['patient_user_id', 'status']);
            $table->index(['provider_user_id', 'status']);
        });

        Schema::create('home_service_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('home_service_request_id')->constrained('home_service_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at');
            $table->index(['home_service_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_service_status_events');
        Schema::dropIfExists('home_service_requests');
    }
};
