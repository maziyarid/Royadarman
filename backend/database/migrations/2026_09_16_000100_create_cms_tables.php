<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('post')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'status', 'published_at']);
        });

        Schema::create('cms_post_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->longText('sanitized_body');
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('cms_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index('parent_id');
        });

        Schema::create('cms_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('cms_categories')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('cms_tags', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('cms_tag_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->timestamps();
            $table->unique(['tag_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('cms_post_category', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('cms_categories')->cascadeOnDelete();
            $table->primary(['post_id', 'category_id']);
        });

        Schema::create('cms_post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('cms_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 50)->default('public-cms');
            $table->string('storage_key');
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->string('sha256', 64)->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->string('caption', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('cms_media_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('cms_media')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('alt_text', 300)->nullable();
            $table->string('caption', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['media_id', 'locale']);
        });

        Schema::create('cms_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 40)->index();
            $table->string('slug', 60)->index();
            $table->timestamps();
            $table->unique('slug');
        });

        Schema::create('cms_menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->timestamps();
            $table->unique(['menu_id', 'locale']);
        });

        Schema::create('cms_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->nullOnDelete();
            $table->string('item_type', 20)->default('custom');
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('linkable_type', 100)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('target', 20)->default('_self');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        Schema::create('cms_menu_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('cms_menu_items')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('label');
            $table->timestamps();
            $table->unique(['menu_item_id', 'locale']);
        });

        Schema::create('cms_seo_metadata', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('locale', 5)->index();
            $table->string('seo_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('robots_directive', 60)->default('index, follow');
            $table->string('og_title', 200)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('twitter_card', 20)->default('summary_large_image');
            $table->string('schema_type', 40)->nullable();
            $table->json('schema_data')->nullable();
            $table->string('focus_keyword', 120)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id', 'locale']);
            $table->unique(['entity_type', 'entity_id', 'locale']);
        });

        Schema::create('cms_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 500)->unique();
            $table->string('destination_url', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 100)->nullable();
            $table->string('author_email', 200)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cms_comments')->nullOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->string('user_ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at']);
        });

        Schema::create('cms_post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('editor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->longText('body');
            $table->string('action', 20)->default('update');
            $table->timestamps();
            $table->index(['post_id', 'locale', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_revisions');
        Schema::dropIfExists('cms_comments');
        Schema::dropIfExists('cms_redirects');
        Schema::dropIfExists('cms_seo_metadata');
        Schema::dropIfExists('cms_menu_item_translations');
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menu_translations');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_media_translations');
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_post_category');
        Schema::dropIfExists('cms_tag_translations');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_category_translations');
        Schema::dropIfExists('cms_categories');
        Schema::dropIfExists('cms_post_translations');
        Schema::dropIfExists('cms_posts');
    }
};
