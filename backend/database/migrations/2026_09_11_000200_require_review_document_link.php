<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An OPG clinical review must remain traceable to the exact approved
        // document reviewed. The preceding migration temporarily uses
        // ON DELETE SET NULL while this column is nullable, so replace that
        // foreign-key action before enforcing the non-null invariant.
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->dropForeign(['clinical_document_id']);
        });

        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreignUlid('clinical_document_id')->nullable(false)->change();
        });

        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreign('clinical_document_id')
                ->references('id')
                ->on('clinical_documents')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->dropForeign(['clinical_document_id']);
        });

        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreignUlid('clinical_document_id')->nullable()->change();
        });

        Schema::table('review_revisions', function (Blueprint $table): void {
            $table->foreign('clinical_document_id')
                ->references('id')
                ->on('clinical_documents')
                ->nullOnDelete();
        });
    }
};
