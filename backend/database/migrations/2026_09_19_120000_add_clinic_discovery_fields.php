<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clinics', 'latitude')) {
            Schema::table('clinics', function (Blueprint $table): void {
                $table->decimal('latitude', 9, 6)->nullable();
            });
        }

        if (! Schema::hasColumn('clinics', 'longitude')) {
            Schema::table('clinics', function (Blueprint $table): void {
                $table->decimal('longitude', 9, 6)->nullable();
            });
        }

        if (! Schema::hasColumn('clinics', 'location_recorded_at')) {
            Schema::table('clinics', function (Blueprint $table): void {
                $table->timestamp('location_recorded_at')->nullable();
            });
        }

        if (! Schema::hasIndex('clinics', ['latitude', 'longitude'])) {
            Schema::table('clinics', function (Blueprint $table): void {
                $table->index(['latitude', 'longitude']);
            });
        }

        if (! Schema::hasTable('clinic_service_capabilities')) {
            Schema::create('clinic_service_capabilities', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('service_type', 40);
                $table->string('suitability_status', 24);
                $table->timestamp('attested_at');
                $table->foreignId('attested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['clinic_id', 'service_type']);
                $table->index(
                    ['service_type', 'suitability_status'],
                    'clinic_svc_type_suitability_idx'
                );
            });

            return;
        }

        // Recovery path for an interrupted MySQL/MariaDB CREATE TABLE sequence:
        // the table can exist while this secondary index is the only missing step.
        if (! Schema::hasIndex('clinic_service_capabilities', ['service_type', 'suitability_status'])) {
            Schema::table('clinic_service_capabilities', function (Blueprint $table): void {
                $table->index(
                    ['service_type', 'suitability_status'],
                    'clinic_svc_type_suitability_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_service_capabilities');

        if (Schema::hasIndex('clinics', ['latitude', 'longitude'])) {
            Schema::table('clinics', function (Blueprint $table): void {
                $table->dropIndex(['latitude', 'longitude']);
            });
        }

        $columns = array_values(array_filter(
            ['latitude', 'longitude', 'location_recorded_at'],
            fn (string $column): bool => Schema::hasColumn('clinics', $column)
        ));

        if ($columns !== []) {
            Schema::table('clinics', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
