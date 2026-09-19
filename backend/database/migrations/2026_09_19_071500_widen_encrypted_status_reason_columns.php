<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypted Eloquent casts store Laravel ciphertext, not the plaintext max:200.
 * VARCHAR(200) is enough on SQLite (which ignores length) and fails on MariaDB
 * strict mode as soon as a short reason such as "patient_confirmed" is encrypted.
 * case_status_events.reason / audit_events.reason are already TEXT.
 *
 * down() is intentionally a no-op: restoring VARCHAR(200) is data-destructive
 * once any encrypted value has been written (strict 1406, or silent truncation
 * that makes ciphertext undecryptable). Keep these columns TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_service_status_events', function (Blueprint $table): void {
            $table->text('reason')->nullable()->change();
        });

        Schema::table('home_service_requests', function (Blueprint $table): void {
            $table->text('cancel_reason')->nullable()->change();
        });

        Schema::table('support_status_events', function (Blueprint $table): void {
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        // no-op: encrypted reason / cancel_reason columns must remain TEXT
    }
};
