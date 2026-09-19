<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamp('location_recorded_at')->nullable();
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('clinic_service_capabilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('service_type', 40);
            $table->string('suitability_status', 24);
            $table->timestamp('attested_at');
            $table->foreignId('attested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['clinic_id', 'service_type']);
            $table->index(['service_type', 'suitability_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_service_capabilities');
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['latitude', 'longitude', 'location_recorded_at']);
        });
    }
};
