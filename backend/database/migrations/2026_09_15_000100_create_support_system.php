<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('case_id')->nullable()->constrained('patient_cases')->cascadeOnDelete();
            $table->string('subject', 200)->nullable();
            $table->string('category', 50)->default('general')->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('source_language', 5)->default('fa');
            $table->timestamps();
            $table->index(['patient_user_id', 'status']);
            $table->index(['assignee_user_id', 'status']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->text('body');
            $table->string('source_language', 5)->default('fa');
            $table->timestamp('created_at');
            $table->index(['conversation_id', 'is_internal', 'created_at']);
        });

        Schema::create('support_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_status_events');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
