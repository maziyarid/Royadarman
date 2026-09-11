<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An OPG clinical review must be traceable to the exact approved document
        // reviewed. Make the FK non-nullable now that every review path requires it.
        // Existing deployed rows (if any) would need backfilling before applying;
        // a fresh deployment has none.
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreignUlid('clinical_document_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreignUlid('clinical_document_id')->nullable()->change();
        });
    }
};
