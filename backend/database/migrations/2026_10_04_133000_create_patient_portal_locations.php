<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_contact_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('province', 60);
            $table->string('city', 100);
            $table->text('neighborhood')->nullable();
            $table->text('address')->nullable();
            $table->text('contact_email')->nullable();
            $table->text('birth_date')->nullable();
            $table->text('postal_code')->nullable();
            $table->string('preferred_contact_time', 16)->default('any');
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->timestamp('location_consented_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('case_locations', function (Blueprint $table): void {
            $table->foreignUlid('case_id')->primary()->constrained('patient_cases')->cascadeOnDelete();
            $table->string('province', 60);
            $table->string('city', 100);
            $table->text('neighborhood')->nullable();
            $table->text('address')->nullable();
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->timestamp('location_consented_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $hasPatientData = Schema::hasTable('patient_contact_profiles')
            && DB::table('patient_contact_profiles')->exists();
        $hasCaseData = Schema::hasTable('case_locations')
            && DB::table('case_locations')->exists();

        if ($hasPatientData || $hasCaseData) {
            throw new RuntimeException('Refusing to drop patient portal location tables after data exists.');
        }

        Schema::dropIfExists('case_locations');
        Schema::dropIfExists('patient_contact_profiles');
    }
};
