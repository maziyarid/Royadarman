<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_grants', function (Blueprint $table): void {
            $table->foreignUlid('consent_event_id')->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('referral_grants', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->change();
            $table->foreignUlid('consent_event_id')->nullable()->change();
        });
    }
};
