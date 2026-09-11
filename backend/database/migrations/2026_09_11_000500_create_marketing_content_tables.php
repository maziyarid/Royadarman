<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slug', 120);
            $table->string('locale', 5);
            $table->string('title', 180);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });

        Schema::create('marketing_page_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('marketing_page_id')->constrained('marketing_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 180);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('status', 20);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['marketing_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_page_revisions');
        Schema::dropIfExists('marketing_pages');
    }
};
