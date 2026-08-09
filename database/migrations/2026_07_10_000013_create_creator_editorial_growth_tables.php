<?php

use App\Enums\EditorialRevisionStatus;
use App\Enums\InstructorProfileStatus;
use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use App\Enums\ScheduledPublicationStatus;
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
        Schema::create('instructor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('avatar_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->text('credentials')->nullable();
            $table->string('expertise')->nullable();
            $table->string('website_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('status', 32)->default(InstructorProfileStatus::Draft->value)->index();
            $table->boolean('is_verified_expert')->default(false)->index();
            $table->boolean('accepts_revenue_share')->default(false)->index();
            $table->string('payout_currency', 3)->default('USD');
            $table->string('payout_account_reference')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('author_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 32)->default('slate');
            $table->boolean('marks_verified_expert')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('author_badge_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['author_badge_id', 'user_id']);
        });

        Schema::create('creator_analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_date')->index();
            $table->string('period', 16)->default('daily')->index();
            $table->unsignedInteger('blog_views')->default(0);
            $table->unsignedInteger('course_views')->default(0);
            $table->unsignedInteger('lesson_views')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('enrollments_count')->default(0);
            $table->unsignedBigInteger('course_revenue_cents')->default(0);
            $table->unsignedBigInteger('ad_revenue_cents')->default(0);
            $table->unsignedInteger('engagement_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_date', 'period']);
        });

        Schema::create('editorial_revisions', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('editorialable');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default(EditorialRevisionStatus::Draft->value)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviewer_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_path')->nullable();
            $table->text('body');
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_publications', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('publishable');
            $table->foreignId('editorial_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publish_at')->index();
            $table->string('timezone')->default('UTC');
            $table->string('status', 32)->default(ScheduledPublicationStatus::Scheduled->value)->index();
            $table->timestamp('published_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('revenue_share_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default(RevenueShareRuleType::Instructor->value)->index();
            $table->string('status', 32)->default(RevenueShareRuleStatus::Draft->value)->index();
            $table->decimal('share_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('fixed_amount_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_share_rules');
        Schema::dropIfExists('scheduled_publications');
        Schema::dropIfExists('reviewer_comments');
        Schema::dropIfExists('editorial_revisions');
        Schema::dropIfExists('creator_analytics_snapshots');
        Schema::dropIfExists('author_badge_user');
        Schema::dropIfExists('author_badges');
        Schema::dropIfExists('instructor_profiles');
    }
};
