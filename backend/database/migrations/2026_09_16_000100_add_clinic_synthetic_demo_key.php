<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('synthetic_demo_key', 64)->nullable()->unique()->after('area_code');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropUnique(['synthetic_demo_key']);
            $table->dropColumn('synthetic_demo_key');
        });
    }
};
