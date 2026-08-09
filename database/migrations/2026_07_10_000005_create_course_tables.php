<?php

use App\Enums\PublishStatus;
use App\Enums\VideoType;
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
        Schema::create('course_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $this->seoColumns($table);
            $table->timestamps();
        });

        Schema::create('course_subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $this->seoColumns($table);
            $table->timestamps();

            $table->unique(['course_category_id', 'slug']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('intro_video_url')->nullable();
            $table->string('level')->nullable();
            $table->string('language', 16)->default('en');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_free')->default(false);
            $this->publishColumns($table);
            $this->seoColumns($table);
            $table->timestamps();
        });

        Schema::create('course_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $this->publishColumns($table);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
        });

        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('order_number')->default(0);
            $table->longText('content')->nullable();
            $table->string('video_type', 32)->default(VideoType::None->value);
            $table->string('video_url')->nullable();
            $table->foreignId('video_file_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->unsignedSmallInteger('preview_word_limit')->nullable();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_questions')->default(true);
            $this->publishColumns($table);
            $this->seoColumns($table);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->index(['course_id', 'order_number']);
        });

        Schema::create('course_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $this->publishColumns($table);
            $table->timestamps();
        });

        Schema::create('course_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('course_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 32)->default(PublishStatus::Pending->value)->index();
            $table->timestamps();
        });

        Schema::create('course_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('body');
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->string('status', 32)->default(PublishStatus::Pending->value)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_questions');
        Schema::dropIfExists('course_comments');
        Schema::dropIfExists('course_faqs');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_subcategories');
        Schema::dropIfExists('course_categories');
    }

    private function publishColumns(Blueprint $table): void
    {
        $table->string('status', 32)->default(PublishStatus::Draft->value)->index();
        $table->timestamp('published_at')->nullable()->index();
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
