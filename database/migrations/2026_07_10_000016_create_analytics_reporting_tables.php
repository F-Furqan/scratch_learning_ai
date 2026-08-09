<?php

use App\Enums\AnalyticsEventType;
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
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->string('event_type', 64)->default(AnalyticsEventType::LandingPageView->value)->index();
            $table->string('event_name', 128)->nullable()->index();
            $table->nullableMorphs('eventable');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('source', 128)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['visitor_id', 'event_type']);
            $table->index(['user_id', 'event_type']);
        });

        Schema::create('analytics_funnel_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('funnel_key', 128)->index();
            $table->date('period_date')->index();
            $table->string('period', 16)->default('daily')->index();
            $table->string('stage', 128);
            $table->unsignedInteger('stage_order')->default(0);
            $table->unsignedInteger('visitors_count')->default(0);
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->unsignedInteger('conversion_rate_basis_points')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['funnel_key', 'period_date', 'period', 'stage'], 'analytics_funnel_period_stage_uniq');
        });

        Schema::create('cohort_retention_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('cohort_month')->index();
            $table->unsignedInteger('period_number')->default(0);
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('retained_users_count')->default(0);
            $table->unsignedInteger('retention_rate_basis_points')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['cohort_month', 'period_number'], 'cohort_retention_period_uniq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cohort_retention_snapshots');
        Schema::dropIfExists('analytics_funnel_snapshots');
        Schema::dropIfExists('analytics_events');
    }
};
