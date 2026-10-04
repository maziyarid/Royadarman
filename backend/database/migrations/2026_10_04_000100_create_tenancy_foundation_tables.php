<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table): void {
            // Retain the existing clinic ULID as the tenant identity; no implicit parent tenancy.
            $table->foreignUlid('clinic_id')->primary()->constrained('clinics')->restrictOnDelete();
            $table->string('display_name', 160);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('clinic_branches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('clinic_id');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('clinic_id')->references('clinic_id')->on('organisations')->restrictOnDelete();
            $table->unique(['clinic_id', 'code'], 'branches_clinic_code_unique');
            $table->unique(['clinic_id', 'id'], 'branches_clinic_id_unique');
        });
        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('clinic_id');
            $table->ulid('branch_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('workspace_role', 32);
            $table->timestamp('active_from');
            $table->timestamp('active_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['clinic_id', 'branch_id'], 'workspace_branch_tenant_fk')
                ->references(['clinic_id', 'id'])->on('clinic_branches')->restrictOnDelete();
            // Non-null branch keys give MariaDB an enforceable natural assignment uniqueness key.
            $table->unique(['branch_id', 'user_id', 'workspace_role'], 'workspace_assignment_unique');
            $table->index(['user_id', 'revoked_at', 'active_until'], 'workspace_actor_current_index');
        });
    }

    public function down(): void
    {
        // Source rollback must retain new tenant data. Populated schema requires reviewed forward repair.
        foreach (['workspace_memberships', 'clinic_branches', 'organisations'] as $name) {
            if (Schema::hasTable($name) && DB::table($name)->exists()) {
                throw new RuntimeException('Refusing destructive tenancy rollback: '.$name.' contains records.');
            }
        }
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('clinic_branches');
        Schema::dropIfExists('organisations');
    }
};
