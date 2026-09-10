<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreignUlid('clinical_document_id')->nullable()->after('case_id')->constrained('clinical_documents')->nullOnDelete();
            $table->index(['case_id', 'clinical_document_id']);
        });
    }

    public function down(): void
    {
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->dropIndex(['case_id', 'clinical_document_id']);
            $table->dropConstrainedForeignId('clinical_document_id');
        });
    }
};
