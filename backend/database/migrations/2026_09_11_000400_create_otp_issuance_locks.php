<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A durable serialization slot for OTP issuance keyed by phone_hash + purpose.
        // Locking this row (which always exists for a given identity+purpose) before
        // supersession/insertion serialises concurrent first-time issuers, so two
        // simultaneous issuance transactions cannot leave two active challenges —
        // the invariant cannot be defeated merely because no existing active
        // challenge row exists to lockForUpdate.
        Schema::create('otp_issuance_locks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('phone_hash', 64);
            $table->string('purpose', 24)->default('login');
            $table->timestamps();
            $table->unique(['phone_hash', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_issuance_locks');
    }
};
