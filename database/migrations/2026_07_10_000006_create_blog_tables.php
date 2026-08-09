<?php

use App\Enums\PublishStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $this->seoColumns($table);
            $table->timestamps();
        });

        Schema::create('blog_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('featured_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 32)->default(PublishStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->text('rejection_reason')->nullable();
            $this->seoColumns($table);
            $table->timestamps();
        });

        Schema::create('blog_post_tag', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['blog_post_id', 'blog_tag_id']);
        });

        Schema::create('blog_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default(PublishStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('blog_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 32)->default(PublishStatus::Pending->value)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_faqs');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }

    private function seoColumns(Blueprint $table): void
    {
        $table->string('seo_title')->nullable();
        $table->text('seo_description')->nullable();
        $table->string('seo_image')->nullable();
        $table->string('canonical_url')->nullable();
        $table->string('og_title')->nullable();
        $table->text('og_description')->nullable();
        $table->string('twitter_title')->nullable();
        $table->text('twitter_description')->nullable();
        $table->json('schema')->nullable();
    }
};
