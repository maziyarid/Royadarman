<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_assignments', function (Blueprint $table): void {
            $table->foreignId('assigned_by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('case_assignments')->whereNull('assigned_by_user_id')->exists()) {
            throw new \LogicException(
                'Cannot roll back system case-assignment support while assignments with no human actor exist. '
                .'Preserving NULL assigned_by_user_id values is required to retain truthful audit provenance.'
            );
        }

        Schema::table('case_assignments', function (Blueprint $table): void {
            $table->foreignId('assigned_by_user_id')->nullable(false)->change();
        });
    }
};
